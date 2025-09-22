import os
import asyncio
from typing import List, Dict, Any, Optional
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from dotenv import load_dotenv
import logging

# Import Graphiti components
from graphiti import Graphiti
from graphiti.nodes import EntityNode, EpisodeNode
from graphiti.edges import Edge

load_dotenv()

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Pydantic models for API requests
class DocumentEntity(BaseModel):
    """A document or content entity from Laravel app"""
    name: str = Field(..., description="The title or name of the document")
    source_type: str = Field(..., description="Type of source (github, slack, linear, etc.)")
    source_url: Optional[str] = Field(None, description="URL to the original document")
    team_id: int = Field(..., description="Team ID for multi-tenant isolation")
    content_summary: Optional[str] = Field(None, description="Brief summary of document content")

class UserSearchPattern(BaseModel):
    """User search behavior and preferences"""
    user_id: int = Field(..., description="User identifier")
    search_query: str = Field(..., description="The search query")
    team_id: int = Field(..., description="Team ID for multi-tenant isolation")
    clicked_results: List[str] = Field(default=[], description="Document IDs that user clicked")
    search_context: Optional[str] = Field(None, description="Context or category of search")

class MemoryRequest(BaseModel):
    text: str = Field(..., description="Text to add to memory")
    team_id: int = Field(..., description="Team ID for multi-tenant isolation")
    context: Optional[Dict[str, Any]] = Field(default={}, description="Additional context")

class SearchRequest(BaseModel):
    query: str = Field(..., description="Search query")
    team_id: int = Field(..., description="Team ID for multi-tenant isolation")
    user_id: Optional[int] = Field(None, description="User ID for personalization")
    limit: int = Field(default=10, description="Number of results to return")

# FastAPI app
app = FastAPI(title="Graphiti Memory Service", version="1.0.0")

# Global Graphiti instance
graphiti_client = None

async def get_graphiti_client():
    """Get or create Graphiti client"""
    global graphiti_client
    if graphiti_client is None:
        # Configure Graphiti to use MemGraph (Neo4j compatible) with Fireworks
        fireworks_api_key = os.getenv("FIREWORKS_API_KEY")
        openai_api_key = os.getenv("OPENAI_API_KEY")

        # Use Fireworks if available, fallback to OpenAI
        if fireworks_api_key:
            llm_config = {
                "provider": "fireworks",
                "api_key": fireworks_api_key,
                "model": "accounts/fireworks/models/llama-v3p1-8b-instruct",
                "base_url": "https://api.fireworks.ai/inference/v1"
            }
        elif openai_api_key:
            llm_config = {
                "provider": "openai",
                "api_key": openai_api_key,
                "model": "gpt-4o-mini"
            }
        else:
            raise ValueError("Either FIREWORKS_API_KEY or OPENAI_API_KEY must be set")

        graphiti_client = Graphiti(
            neo4j_config={
                "uri": os.getenv("NEO4J_URI", "bolt://memgraph:7687"),
                "username": os.getenv("NEO4J_USER", "horizontal"),
                "password": os.getenv("NEO4J_PASSWORD", "password"),
                "database": os.getenv("NEO4J_DATABASE", "memgraph")
            },
            llm_config=llm_config
        )
        await graphiti_client.build_indices_if_needed()
    return graphiti_client

@app.on_event("startup")
async def startup_event():
    """Initialize Graphiti on startup"""
    logger.info("Initializing Graphiti service...")
    await get_graphiti_client()
    logger.info("Graphiti service initialized successfully")

@app.post("/api/memory/add")
async def add_memory(request: MemoryRequest):
    """Add information to episodic memory"""
    try:
        client = await get_graphiti_client()

        # Add team isolation context
        contextual_text = f"[Team {request.team_id}] {request.text}"

        # Add to episodic memory
        episode_id = await client.add_episode(
            name=f"search_episode_{request.team_id}",
            content=contextual_text,
            context=request.context
        )

        return {"status": "success", "episode_id": episode_id}

    except Exception as e:
        logger.error(f"Error adding memory: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/api/memory/search")
async def search_memory(request: SearchRequest):
    """Search episodic memory with team isolation"""
    try:
        client = await get_graphiti_client()

        # Add team context to query
        team_query = f"[Team {request.team_id}] {request.query}"

        # Search episodic memory
        results = await client.search(
            query=team_query,
            limit=request.limit
        )

        # Filter results to ensure team isolation
        filtered_results = []
        for result in results:
            if f"Team {request.team_id}" in result.get("content", ""):
                filtered_results.append({
                    "content": result.get("content", "").replace(f"[Team {request.team_id}] ", ""),
                    "score": result.get("score", 0),
                    "episode_id": result.get("episode_id"),
                    "timestamp": result.get("timestamp")
                })

        return {"results": filtered_results, "total": len(filtered_results)}

    except Exception as e:
        logger.error(f"Error searching memory: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/api/entities/document")
async def add_document_entity(entity: DocumentEntity):
    """Add a document entity to the knowledge graph"""
    try:
        client = await get_graphiti_client()

        # Create document entity with team isolation
        entity_data = {
            "name": f"[Team {entity.team_id}] {entity.name}",
            "source_type": entity.source_type,
            "source_url": entity.source_url,
            "team_id": entity.team_id,
            "content_summary": entity.content_summary
        }

        entity_id = await client.add_entity(
            entity_type="Document",
            properties=entity_data
        )

        return {"status": "success", "entity_id": entity_id}

    except Exception as e:
        logger.error(f"Error adding document entity: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/api/patterns/search")
async def track_search_pattern(pattern: UserSearchPattern):
    """Track user search patterns for personalization"""
    try:
        client = await get_graphiti_client()

        # Create search pattern episode
        episode_content = f"User {pattern.user_id} searched for '{pattern.search_query}'"
        if pattern.clicked_results:
            episode_content += f" and clicked on documents: {', '.join(pattern.clicked_results)}"

        # Add team context
        contextual_content = f"[Team {pattern.team_id}] {episode_content}"

        episode_id = await client.add_episode(
            name=f"search_pattern_{pattern.user_id}_{pattern.team_id}",
            content=contextual_content,
            context={
                "user_id": pattern.user_id,
                "team_id": pattern.team_id,
                "search_context": pattern.search_context,
                "type": "search_pattern"
            }
        )

        return {"status": "success", "episode_id": episode_id}

    except Exception as e:
        logger.error(f"Error tracking search pattern: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/api/patterns/user/{user_id}")
async def get_user_patterns(user_id: int, team_id: int, limit: int = 10):
    """Get user's historical search patterns for personalization"""
    try:
        client = await get_graphiti_client()

        # Search for user's patterns with team isolation
        query = f"[Team {team_id}] User {user_id} search patterns"

        results = await client.search(
            query=query,
            limit=limit
        )

        # Filter and format results
        patterns = []
        for result in results:
            if f"Team {team_id}" in result.get("content", "") and f"User {user_id}" in result.get("content", ""):
                patterns.append({
                    "content": result.get("content", "").replace(f"[Team {team_id}] ", ""),
                    "context": result.get("context", {}),
                    "timestamp": result.get("timestamp")
                })

        return {"patterns": patterns, "user_id": user_id, "team_id": team_id}

    except Exception as e:
        logger.error(f"Error getting user patterns: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/health")
async def health_check():
    """Health check endpoint"""
    try:
        client = await get_graphiti_client()
        return {"status": "healthy", "service": "graphiti-memory"}
    except Exception as e:
        raise HTTPException(status_code=503, detail=f"Service unhealthy: {str(e)}")

if __name__ == "__main__":
    import uvicorn

    port = int(os.getenv("PORT", 9995))
    reload = os.getenv("HOT_RELOAD_ENABLED", "true").lower() == "true"

    uvicorn.run("main:app", host="0.0.0.0", port=port, reload=reload)