import logging
from psycopg2.extensions import cursor as Cursor
from .jobs.index_batch import index_batch
from src.factories import create_queue, create_db_cursor
from src.services import count_waiting_comments, count_waiting_document_chunks

class GraphBuilder:
    def __init__(self, tenant_id: str):        
        logging.basicConfig(level=logging.INFO)
        
        self.tenant_id = tenant_id
        self.queue = create_queue()
        
    def build_graph_for_tenant(self):
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
        
        for i in range(num_of_batches):
            self.queue.enqueue(index_batch,
                               type,
                               5,   # limit
                               num_of_batches,
                               i+1, # batch serial
                               self.tenant_id)