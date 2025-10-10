import os
import requests
import psycopg2
import logging
from typing import List
from dotenv import load_dotenv
from llama_index.core.indices.property_graph import SimpleLLMPathExtractor
from llama_index.core import Settings, PropertyGraphIndex, Document
from llama_index.llms.fireworks import Fireworks
from llama_index.graph_stores.memgraph import MemgraphPropertyGraphStore
from llama_index.readers.database import DatabaseReader
from src.fireworks_embedding import FireworksEmbedding

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
                 
        self.build_graph(reader, graph_store, cursor)

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

    def create_db_reader(self) -> DatabaseReader:
        uri = self._build_db_uri(self.tenant_id, protocol="postgresql+psycopg2")
        return DatabaseReader(uri=uri)

    def create_graph_store(self) -> MemgraphPropertyGraphStore:
        connection_info = self._get_graph_db_connection_info(self.tenant_id)
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
                where processed = FALSE
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
            
        return transformed_documents

    def build_graph(self, 
                    reader: DatabaseReader, 
                    graph_store: MemgraphPropertyGraphStore,
                    cursor): 
        
        logging.info(f"---- BUILDING GRAPH ----")    
        
        document_count = self.get_document_chunks_count(cursor)       
        limit = 5
        num_of_batches = int(document_count/limit)+1
        
        logging.info(f"Number of documents to process: {document_count}")
        logging.info(f"Number of batches: {num_of_batches}")
        
        for i in range(num_of_batches):
            logging.info(f"Processing batch {i+1}/{num_of_batches}...")
            offset = i*limit
            
            logging.info("Loading documents...")
            documents = self._load_documents(reader=reader,
                                             limit=limit,
                                             offset=offset)
            
            if len(documents) == 0:
                logging.info("All documents are processed")
                break
        
            logging.info(f"Loaded {len(documents)} from offset {offset}")

            logging.info("Running LLM Path Extractor with settings...")
            logging.info(Settings)
            kg_extractor = SimpleLLMPathExtractor(llm=self.llm,
                                                  max_paths_per_chunk=20,
                                                  num_workers=4)
            
            logging.info("LLM Path Extractor finished...")
            
            
            show_progress = False
            if os.getenv("APP_ENV") == "development":
                show_progress = True
            
            logging.info("Creating graph index...")    
            index = PropertyGraphIndex.from_documents(documents,
                                                      llm=self.llm,
                                                      embed_kg_nodes=True,
                                                      embed_model=self.embed_model,
                                                      kg_extractors=[kg_extractor],
                                                      show_progress=show_progress,
                                                      property_graph_store=graph_store)
            logging.info("Graph index created")
        
            logging.info(f"Inserting {len(documents)} documents")
            for n, document in enumerate(documents):
                index.insert(document)
                logging.info(f"Inserting to index: {n+1}/{len(documents)}")
            
            logging.info(f"Updating documents...")
            self.update_documents(documents, cursor)
            logging.info(f"Documents updated")
            
            logging.info(f"batch {i+1}/{num_of_batches} processed")
        
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

    def get_document_chunks_count(self, cursor) -> int:
        cursor.execute("select count(*) from document_chunks")
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
        
        cursor.connection.commit()
        
        logging.info(f"{cursor.rowcount} rows updated")
        
        if cursor.rowcount != len(documents):
            logging.warning(f"Graph building: not all document_chunk rows were processed succesfuly. Expected: {len(documents)}. Actual: {cursor.rowcount}")
  
    def _build_db_uri(self, tenant_id: str, protocol: str) -> str:
        host = os.getenv("DB_HOST")
        port = os.getenv("DB_PORT")
        username = os.getenv("DB_USERNAME")
        password = os.getenv("DB_PASSWORD")  
        return f"{protocol}://{username}:{password}@{host}:{port}/tenant{tenant_id}"