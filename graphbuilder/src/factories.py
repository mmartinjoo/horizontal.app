import os
import psycopg2
import neo4j
import ssl
from redis import Redis
from rq import Queue
from llama_index.readers.database import DatabaseReader
from llama_index.graph_stores.memgraph import MemgraphPropertyGraphStore
from src.services.horizontal_api import get_graph_db_connection_info
from src.graph_store_manager import get_graph_store_for_tenant
from llama_index.core.embeddings import BaseEmbedding
from llama_index.core.llms import CustomLLM
from src.together_embedding import TogetherEmbedding
from src.fireworks_llm import FireworksLLM
from src.together_llm import TogetherLLM
from psycopg2.extensions import cursor as Cursor
from src.services.horizontal_api import get_llm_provider

def create_db_reader(tenant_id: str) -> DatabaseReader:
    """
    Creates a DB connection that is used in LlamaIndex
    """    
    uri = build_db_uri(tenant_id, protocol="postgresql+psycopg2")
    return DatabaseReader(uri=uri)

def create_db_cursor(tenant_id: str) -> Cursor:
    """
    Creates a DB connection that can be used in any context (not LlamaIndex-related)
    """
    uri = build_db_uri(tenant_id=tenant_id, protocol="postgresql")
    conn = psycopg2.connect(uri)
    conn.autocommit = True
    return conn.cursor()

def create_graph_store(tenant_id: str) -> MemgraphPropertyGraphStore:
    """
    Gets or creates a cached graph DB connection for the given tenant.

    Uses a singleton pattern to ensure only one graph store instance per tenant
    per worker process, preventing race conditions during initialization and
    reducing connection overhead.

    Note: Index creation is disabled in the graph store initialization.
    Indexes must be pre-created using the setup_indexes.py script.
    """
    return get_graph_store_for_tenant(tenant_id)

def create_graph_client(tenant_id: str) -> neo4j.Driver:
    """
    Creates a DB connection that can be used in any context (not LlamaIndex-related)
    """
    connection_info = get_graph_db_connection_info(tenant_id)
    return neo4j.GraphDatabase.driver(
        connection_info["url"],
        auth=(connection_info["user"], connection_info["password"])
    )

def create_queue() -> Queue:
    redis = create_redis()
    return Queue(connection=redis, name="default", default_timeout="30m")

def create_redis() -> Redis:
    url = os.getenv("REDIS_URL")
    return Redis.from_url(url)

def create_llm(tenant_id: str) -> CustomLLM:
    provider = get_llm_provider(tenant_id=tenant_id) 
    print(provider)
    if provider == 'fireworks':
        return FireworksLLM(api_key=os.getenv("FIREWORKS_API_KEY"),
                            model_name=os.getenv("FIREWORKS_CHAT_MODEL"))
    else:
        return TogetherLLM(api_key=os.getenv("TOGETHER_API_KEY"),
                            model_name=os.getenv("TOGETHER_CHAT_MODEL"))
        
def create_embed_model() -> BaseEmbedding:
    return TogetherEmbedding()

def build_db_uri(tenant_id: str, protocol: str) -> str:
    host = os.getenv("DB_HOST")
    port = os.getenv("DB_PORT")
    username = os.getenv("DB_USERNAME")
    password = os.getenv("DB_PASSWORD") 
    sslmode = os.getenv("DB_SSLMODE") 
    return f"{protocol}://{username}:{password}@{host}:{port}/tenant{tenant_id}?sslmode={sslmode}"