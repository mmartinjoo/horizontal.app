import os
import asyncio
from fastapi import FastAPI, HTTPException
from llama_index.core.indices.property_graph import SimpleLLMPathExtractor
from llama_index.core import Settings, PropertyGraphIndex
from llama_index.llms.fireworks import Fireworks
from llama_index.graph_stores.memgraph import MemgraphPropertyGraphStore
from llama_index.readers.database import DatabaseReader
from dotenv import load_dotenv
from fireworks_embedding import FireworksEmbedding

load_dotenv()

os.environ["OPENAI_API_KEY"] = os.getenv("FIREWORKS_API_KEY")
llm = Fireworks(
    api_key=os.getenv("FIREWORKS_API_KEY"),
    temperature=0,
    model=os.getenv("LLM_MODEL"),
)

embed_model = FireworksEmbedding()

Settings.llm = llm
Settings.embed_model = embed_model

reader = DatabaseReader(
    uri=os.getenv("DB_URI")
)

async def build_graph():
    documents = await asyncio.to_thread(
        reader.load_data,
        query="""
            select
                document_chunks.id as document_chunk_id,
                documents.title as title,
                document_chunks.body as body,
                documents.source_type as source_type,
                documents.source_url as source_url,
                documents.id as source_document_id,
                'document' as document_type
            from document_chunks
            inner join documents on documents.id = document_chunks.document_id
        """,
        metadata_cols=[
            "title", "source_type", "source_url", "document_chunk_id", "source_document_id", "document_type",
        ],
        excluded_text_cols=[
            "source_type", "source_url", "document_chunk_id", "source_document_id", "document_type",
        ],
    )
    comments = await asyncio.to_thread(
        reader.load_data,
        query="""
            select
                document_comments.id as comment_id,
                document_comments.body as body,
                documents.source_type as source_type,
                documents.source_url as source_url,
                documents.id as parent_document_id,
                'comment' as document_type
            from document_comments
            inner join documents on documents.id = document_comments.document_id
        """,
        metadata_cols=[
            "source_type", "source_url", "comment_id", "parent_document_id", "document_type",
        ],
        excluded_text_cols=[
            "source_type", "source_url", "comment_id", "parent_document_id", "document_type",
        ],
    )
    all_documents = documents + comments
    
    # Run blocking graph operations in thread pool
    def _build_graph_sync():
        graph_store = MemgraphPropertyGraphStore(
            url=os.getenv("GRAPH_DB_URI"),
            username=os.getenv("GRAPH_DB_USER"),
            password=os.getenv("GRAPH_DB_PASSWORD"),            
            database="memgraph",
        )
        kg_extractor = SimpleLLMPathExtractor(
            llm=llm,
            max_paths_per_chunk=20,
            num_workers=4,
        )
        PropertyGraphIndex.from_documents(
            all_documents,
            llm=llm,
            embed_kg_nodes=True,
            kg_extractors=[kg_extractor],
            show_progress=True,
            property_graph_store=graph_store,
        )

    await asyncio.to_thread(_build_graph_sync)

app = FastAPI()

@app.post("/api/build", status_code=202)
async def api_build_graph():
    try:
        asyncio.create_task(build_graph())
        return {"status": "accepted"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == '__main__':
    import uvicorn
    uvicorn.run(app, host='0.0.0.0', port=9998)
