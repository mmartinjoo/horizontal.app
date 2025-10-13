import os
import psycopg2
import logging
from redis import Redis
from rq import Queue
from llama_index.readers.database import DatabaseReader
from llama_index.graph_stores.memgraph import MemgraphPropertyGraphStore
from src.services.horizontal_api import get_graph_db_connection_info
from llama_index.llms.fireworks import Fireworks
from llama_index.core.embeddings import BaseEmbedding
from src.fireworks_embedding import FireworksEmbedding
from psycopg2.extensions import cursor as Cursor

def create_db_reader(tenant_id: str) -> DatabaseReader:
    uri = build_db_uri(tenant_id, protocol="postgresql+psycopg2")
    return DatabaseReader(uri=uri)

def create_db_cursor(tenant_id: str) -> Cursor:
    uri = build_db_uri(tenant_id=tenant_id, protocol="postgresql")
    conn = psycopg2.connect(uri)
    conn.autocommit = True
    return conn.cursor()

def create_graph_store(tenant_id: str) -> MemgraphPropertyGraphStore:
    connection_info = get_graph_db_connection_info(tenant_id)
    return MemgraphPropertyGraphStore(url=connection_info["url"],
                                        username=connection_info["user"],
                                        password=connection_info["password"],
                                        database="memgraph")

def create_queue() -> Queue:
    redis = Redis(host=os.getenv("REDIS_HOST"), port=os.getenv("REDIS_PORT"))
    return Queue(connection=redis, name="default")

def create_llm() -> Fireworks:
    return Fireworks(api_key=os.getenv("FIREWORKS_API_KEY"),
              temperature=0,
              model=os.getenv("LLM_MODEL"))
    
def create_embed_model() -> BaseEmbedding:
    return FireworksEmbedding()

def build_db_uri(tenant_id: str, protocol: str) -> str:
    host = os.getenv("DB_HOST")
    port = os.getenv("DB_PORT")
    username = os.getenv("DB_USERNAME")
    password = os.getenv("DB_PASSWORD")  
    return f"{protocol}://{username}:{password}@{host}:{port}/tenant{tenant_id}"