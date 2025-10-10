import os
import requests
import psycopg2
from typing import List
from dotenv import load_dotenv
from llama_index.core.indices.property_graph import SimpleLLMPathExtractor
from llama_index.core import Settings, PropertyGraphIndex, Document
from llama_index.llms.fireworks import Fireworks
from llama_index.graph_stores.memgraph import MemgraphPropertyGraphStore
from llama_index.readers.database import DatabaseReader
from src.fireworks_embedding import FireworksEmbedding

class GraphBuilder:
    def __init__(self):
        load_dotenv()
        self._setup_llm_and_embeddings()
        
    def build_graph_for_tenant(self, tenant_id: str):
        reader = self.create_db_reader(tenant_id)
        graph_store = self.create_graph_store(tenant_id)
        count = self.get_documents_count(reader, tenant_id)       
        self.build_graph(reader, graph_store, count)

    def _setup_llm_and_embeddings(self):
        os.environ["OPENAI_API_KEY"] = os.getenv("FIREWORKS_API_KEY")
        self.llm = Fireworks(
            api_key=os.getenv("FIREWORKS_API_KEY"),
            temperature=0,
            model=os.getenv("LLM_MODEL"),
        )

        self.embed_model = FireworksEmbedding()

        Settings.llm = self.llm
        Settings.embed_model = self.embed_model

    def create_db_reader(self, tenant_id: str) -> DatabaseReader:
        uri = self._build_db_uri(tenant_id, protocol="postgresql+psycopg2")
        return DatabaseReader(uri=uri)

    def create_graph_store(self, tenant_id: str) -> MemgraphPropertyGraphStore:
        connection_info = self._get_graph_db_connection_info(tenant_id)
        return MemgraphPropertyGraphStore(
            url=connection_info["url"],
            username=connection_info["user"],
            password=connection_info["password"],
            database="memgraph"
        )

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
        
    def _load_documents(self, reader: DatabaseReader, limit: int, offset: int) -> List[Document]:
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
                limit {limit}
                offset {offset}
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
            
        return documents

    def build_graph(self, 
                    reader: DatabaseReader, 
                    graph_store: MemgraphPropertyGraphStore, 
                    document_count: int): 
        
           
        num_of_batches = int(document_count/100)+1
        for i in range(num_of_batches):
            offset = i*100
            
            documents = self._load_documents(reader=reader,
                                             limit=100,
                                             offset=offset)
        
            kg_extractor = SimpleLLMPathExtractor(llm=self.llm,
                                                  max_paths_per_chunk=20,
                                                  num_workers=4)
            
            index = PropertyGraphIndex.from_documents(documents,
                                                      llm=self.llm,
                                                      embed_kg_nodes=True,
                                                      kg_extractors=[kg_extractor],
                                                      show_progress=False,
                                                      property_graph_store=graph_store)
        
            for document in documents:
                index.insert(document)
        
        # comments = reader.load_data(
        #     query="""
        #         select
        #             document_comments.id as comment_id,
        #             document_comments.body as body,
        #             documents.source_type as source_type,
        #             documents.source_url as source_url,
        #             documents.id as parent_document_id,
        #             'comment' as document_type
        #         from document_comments
        #         inner join documents on documents.id = document_comments.document_id
        #     """,
        #     metadata_cols=[
        #         "source_type", "source_url", "comment_id", "parent_document_id", "document_type",
        #     ],
        #     excluded_text_cols=[
        #         "source_type", "source_url", "comment_id", "parent_document_id", "document_type",
        #     ],
        # )

        # # Original comments are copied to custom documents because the LLM also received
        # # the metadata and created graph nodes for thing like "source_url" etc
        # # I didn't find a better solution
        # transformed_comments = []
        # for comment in comments:
        #     doc = Document(
        #         text=comment.get_content(),
        #         metadata=comment.metadata,
        #         excluded_llm_metadata_keys=["source_type", "source_url", "comment_id", "parent_document_id", "document_type"],
        #         excluded_embed_metadata_keys=["comment_id", "parent_document_id"],
        #     )
        #     transformed_comments.append(doc)

        # all_documents = transformed_documents + transformed_comments

    def get_documents_count(self, reader: DatabaseReader, tenant_id: str) -> int:
        """Get the total count of documents in the documents table for a tenant."""
        uri = self._build_db_uri(tenant_id, protocol="postgresql")
        conn = psycopg2.connect(uri)
        cursor = conn.cursor()
        cursor.execute("SELECT COUNT(*) FROM documents")
        count = cursor.fetchone()[0]
        return count
  
    def _build_db_uri(self, tenant_id: str, protocol: str) -> str:
        host = os.getenv("DB_HOST")
        port = os.getenv("DB_PORT")
        username = os.getenv("DB_USERNAME")
        password = os.getenv("DB_PASSWORD")  
        return f"{protocol}://{username}:{password}@{host}:{port}/tenant{tenant_id}"