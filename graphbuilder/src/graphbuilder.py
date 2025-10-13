import logging
from psycopg2.extensions import cursor as Cursor
from .jobs.index_batch import index_batch
from .jobs.indexing_finished import indexing_finished
from src.factories import create_queue, create_db_cursor, create_graph_client
from src.services import count_waiting_comments, count_waiting_document_chunks

class GraphBuilder:
    def __init__(self, tenant_id: str):        
        logging.basicConfig(level=logging.INFO)
        
        self.tenant_id = tenant_id
        self.queue = create_queue()
        
    def build_graph_for_tenant(self):
        # This makes possible to run queries in parallel without conflicts
        # Storage mode is set back to IN_MEMORY_TRANSACTIONAL oncxe all jobs have been finished
        graph_client = create_graph_client(tenant_id=self.tenant_id)
        with graph_client.session() as session:
            session.run("STORAGE MODE IN_MEMORY_ANALYTICAL")
            
        cursor = create_db_cursor(tenant_id=self.tenant_id)
        self.build_graph(type="comment",
                         cursor=cursor)
        
        # self.build_graph(type="document_chunk",
                        #  cursor=cursor)
    
    def build_graph(self, 
                    type: str,
                    cursor: Cursor):
        
        logging.info(f"---- BUILDING GRAPH FROM {type}s BATCH ----")   
        
        if type == "document_chunk":
            count = count_waiting_document_chunks(cursor=cursor)
        else:
            count = count_waiting_comments(cursor=cursor)
        
        limit = 5
        num_of_batches = int(count/limit)+1
        
        if count == 0:
            logging.info(f"No {type}s to process")
            return
        
        logging.info(f"Number of {type}s to process: {count}")
        logging.info(f"Number of batches: {num_of_batches}")
        
        job_ids = []
        for i in range(num_of_batches):
            job = self.queue.enqueue(index_batch,
                               type,
                               5,   # limit
                               num_of_batches,
                               i+1, # batch serial
                               self.tenant_id)

            job_ids.append(job.id)
            
        self.queue.enqueue(indexing_finished,
                           tenant_id=self.tenant_id,
                           depends_on=job_ids)