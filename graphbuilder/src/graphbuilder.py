import os
import requests
import psycopg2
import logging
from typing import List
from dotenv import load_dotenv
from llama_index.core import Settings, Document
from llama_index.llms.fireworks import Fireworks
from llama_index.graph_stores.memgraph import MemgraphPropertyGraphStore
from llama_index.readers.database import DatabaseReader
from src.fireworks_embedding import FireworksEmbedding
from .jobs.index_batch import index_batch

class GraphBuilder:
    def __init__(self, tenant_id: str):
        load_dotenv()
        logging.basicConfig(level=logging.INFO)
        
        self.tenant_id = tenant_id
        self._setup_llm_and_embeddings()
        
    def build_graph_for_tenant(self):
        uri = self._build_db_uri(self.tenant_id, protocol="postgresql")
        conn = psycopg2.connect(uri)
        conn.autocommit = True
        cursor = conn.cursor()
        
        reader = self.create_db_reader()
        graph_store = self.create_graph_store()        
                 
        # self.build_graph(reader=reader, 
        #                  type="document_chunk",
        #                  graph_store=graph_store, 
        #                  cursor=cursor)

        self.build_graph(reader=reader, 
                         type="comment",
                         graph_store=graph_store, 
                         cursor=cursor)
    
    def build_graph(self,
                    type: str,
                    reader: DatabaseReader, 
                    graph_store: MemgraphPropertyGraphStore,
                    cursor):
        if type == "document_chunk":
            count_fn = self.get_document_chunks_count
            load_fn = self._load_documents
            update_fn = self.update_documents
            
        if type == "comment":
            count_fn = self.get_comments_count
            load_fn = self._load_comments
            update_fn = self.update_comments
            
        self.build_graph_from(type=type,
                              reader=reader,
                              graph_store=graph_store,
                              cursor=cursor,
                              count_fn=count_fn,
                              load_fn=load_fn,
                              update_fn=update_fn)
            
    def build_graph_from(self, 
                         type: str,
                         reader: DatabaseReader, 
                         graph_store: MemgraphPropertyGraphStore,
                         cursor,
                         count_fn,
                         load_fn,
                         update_fn):
        
        logging.info(f"---- BUILDING GRAPH FROM {type}s BATCH ----")    
        
        count = count_fn(cursor)       
        limit = 5
        num_of_batches = int(count/limit)+1
        
        if count == 0:
            logging.info(f"No {type}s to process")
            return
        
        logging.info(f"Number of {type}s to process: {count}")
        logging.info(f"Number of batches: {num_of_batches}")
        
        for i in range(num_of_batches):
            index_batch(type=type,
                        reader=reader,
                        limit=5,
                        load_fn=load_fn,
                        update_fn=update_fn,
                        llm=self.llm,
                        embed_model=self.embed_model,
                        graph_store=graph_store,
                        cursor=cursor,
                        num_of_batches=num_of_batches,
                        batch_serial=i+1,
                        tenant_id=self.tenant_id)

    def _get_graph_db_connection_info(self, tenant_id: str):
        resp = requests.get(f"{os.getenv('HORIZONTAL_API_URL')}/api/tenants/{tenant_id}")
        if resp.status_code != 200:
            raise RuntimeError("Failed to get tenant connection info")

        data = resp.json()
        return {
            "url": f"bolt://{data['graph_db_connection']['host']}:{data['graph_db_connection']['port']}",
            "user": data["graph_db_connection"]["user"],
            "password": data["graph_db_connection"]["password"],
        }
        
    def _load_documents(self, reader: DatabaseReader, limit: int) -> List[Document]:
        documents = reader.load_data(
            query=f"""
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
                where document_chunks.processed = FALSE                
                limit {limit}
            """,
            metadata_cols=[
                "title", "source_type", "source_url", "document_chunk_id", "source_document_id", "document_type",
            ],
            excluded_text_cols=[
                "source_type", "source_url", "document_chunk_id", "source_document_id", "document_type",
            ],
        )
        
        # Original comments are copied to custom documents because the LLM also received
        # the metadata and created graph nodes for thing like "source_url" etc
        # I didn't find a better solution
        transformed_documents = []
        for document in documents:
            doc = Document(
                text=document.get_content(),
                metadata=document.metadata,
                excluded_llm_metadata_keys=["source_type", "source_url", "document_chunk_id", "source_document_id", "document_type"],
                excluded_embed_metadata_keys=["source_url", "document_chunk_id", "source_document_id", "document_type"],
            )
            transformed_documents.append(doc)
            
        return transformed_documents
            
    def _load_comments(self, reader: DatabaseReader, limit: int) -> List[Document]:
        comments = reader.load_data(
            query=f"""
                select
                    document_comments.id as comment_id,
                    document_comments.body as body,
                    documents.source_type as source_type,
                    documents.source_url as source_url,
                    documents.id as parent_document_id,
                    'comment' as document_type
                from document_comments
                inner join documents on documents.id = document_comments.document_id
                where document_comments.processed = FALSE                
                limit {limit}
            """,
            metadata_cols=[
                "source_type", "source_url", "comment_id", "parent_document_id", "document_type",
            ],
            excluded_text_cols=[
                "source_type", "source_url", "comment_id", "parent_document_id", "document_type",
            ],
        )

        # Original comments are copied to custom documents because the LLM also received
        # the metadata and created graph nodes for thing like "source_url" etc
        # I didn't find a better solution
        transformed_comments = []
        for comment in comments:
            doc = Document(
                text=comment.get_content(),
                metadata=comment.metadata,
                excluded_llm_metadata_keys=["source_type", "source_url", "comment_id", "parent_document_id", "document_type"],
                excluded_embed_metadata_keys=["comment_id", "parent_document_id"],
            )
            transformed_comments.append(doc)

        return transformed_comments

    def get_document_chunks_count(self, cursor) -> int:
        cursor.execute("select count(*) from document_chunks where processed = FALSE")
        return cursor.fetchone()[0]
    
    def get_comments_count(self, cursor) -> int:
        cursor.execute("select count(*) from document_comments where processed = FALSE")
        return cursor.fetchone()[0]
    
    def update_documents(self, documents: List[Document], cursor):
        if len(documents) == 0:
            return
        
        doc_ids = [doc.metadata["document_chunk_id"] for doc in documents]
        doc_ids_str = ",".join([str(id) for id in doc_ids])
        
        cursor.execute(f"""
                       update document_chunks
                       set processed = TRUE
                       where id in ({doc_ids_str})                    
                       """)
        
        logging.info(f"{cursor.rowcount} rows updated")
        
        if cursor.rowcount != len(documents):
            logging.warning(f"Graph building: not all document_chunk rows were processed succesfuly. Expected: {len(documents)}. Actual: {cursor.rowcount}")
            
    def update_comments(self, comments: List[Document], cursor):
        if len(comments) == 0:
            return
        
        comm_id = [doc.metadata["comment_id"] for doc in comments]
        comm_id_str = ",".join([str(id) for id in comm_id])
        
        cursor.execute(f"""
                       update document_comments
                       set processed = TRUE
                       where id in ({comm_id_str})                    
                       """)
        
        logging.info(f"{cursor.rowcount} rows updated")
        
        if cursor.rowcount != len(comments):
            logging.warning(f"Graph building: not all comments rows were processed succesfuly. Expected: {len(comments)}. Actual: {cursor.rowcount}")
  
    def _build_db_uri(self, tenant_id: str, protocol: str) -> str:
        host = os.getenv("DB_HOST")
        port = os.getenv("DB_PORT")
        username = os.getenv("DB_USERNAME")
        password = os.getenv("DB_PASSWORD")  
        return f"{protocol}://{username}:{password}@{host}:{port}/tenant{tenant_id}"
    
    def _setup_llm_and_embeddings(self):
        os.environ["OPENAI_API_KEY"] = os.getenv("FIREWORKS_API_KEY")
        self.llm = Fireworks(api_key=os.getenv("FIREWORKS_API_KEY"),
                             temperature=0,
                             model=os.getenv("LLM_MODEL"))

        self.embed_model = FireworksEmbedding()

        Settings.llm = self.llm
        Settings.embed_model = self.embed_model

    def create_db_reader(self) -> DatabaseReader:
        uri = self._build_db_uri(self.tenant_id, protocol="postgresql+psycopg2")
        return DatabaseReader(uri=uri)

    def create_graph_store(self) -> MemgraphPropertyGraphStore:
        connection_info = self._get_graph_db_connection_info(self.tenant_id)
        return MemgraphPropertyGraphStore(url=connection_info["url"],
                                          username=connection_info["user"],
                                          password=connection_info["password"],
                                          database="memgraph")