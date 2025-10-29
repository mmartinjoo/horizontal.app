import logging
from typing import List
from psycopg2.extensions import cursor as Cursor
from .jobs.index_batch import index_batch
from .jobs.indexing_finished import indexing_finished
from src.factories import create_queue, create_db_cursor, create_graph_client
from src.services import count_waiting_comments, count_waiting_document_chunks, get_waiting_ids, create_workflow_bucket, add_bucket_items

class GraphBuilder:
    def __init__(self, tenant_id: str, workflow_step_id: int):
        self.tenant_id = tenant_id
        self.workflow_step_id = workflow_step_id
        self.queue = create_queue()
        
    def build_graph_for_tenant(self):
        # This makes possible to run queries in parallel without conflicts
        # Storage mode is set back to IN_MEMORY_TRANSACTIONAL oncxe all jobs have been finished
        graph_client = create_graph_client(tenant_id=self.tenant_id)
        with graph_client.session() as session:
            session.run("STORAGE MODE IN_MEMORY_ANALYTICAL")
            
        cursor = create_db_cursor(tenant_id=self.tenant_id)
        doc_job_ids = self.build_graph(type="document_chunks",
                         cursor=cursor)
        comment_job_ids = self.build_graph(type="document_comments",
                         cursor=cursor)
        
        job_ids = doc_job_ids+comment_job_ids
        
        self.queue.enqueue(indexing_finished,
                           tenant_id=self.tenant_id,
                           depends_on=job_ids)
    
    def build_graph(self, 
                    type: str,
                    cursor: Cursor) -> List[str]:
        
        logging.warning(f"---- BUILDING GRAPH FROM {type} BATCH ----")   
        
        if type == "document_chunks":
            count = count_waiting_document_chunks(cursor=cursor)
        else:
            count = count_waiting_comments(cursor=cursor)
        
        limit = 5
        num_of_batches = int(count/limit)+1
        
        if count == 0:
            logging.warning(f"No {type} to process")
            return []
        
        logging.warning(f"Number of {type} to process: {count}")
        logging.warning(f"Number of batches: {num_of_batches}")
        
        job_ids = []
        for i in range(num_of_batches):
            limit = 5
            offset = limit*i
            ids = get_waiting_ids(cursor=cursor, table=type, limit=limit, offset=offset)
            if len(ids) == 0:
                continue
            
            workflow_bucket = {
                "title": f"{type} batch {i+1}/{num_of_batches}",
                "overall_items": limit,
                "workflow_step_id": self.workflow_step_id,
            }
            bucket = create_workflow_bucket(self.tenant_id, workflow_bucket)
            bucket_item_ids = add_bucket_items(tenant_id=self.tenant_id,
                             bucket_id=bucket["id"],
                             ids=ids,
                             type=type)
            job = self.queue.enqueue(index_batch,
                               type,
                               ids,
                               self.tenant_id,
                               bucket_item_ids,
                               job_timeout="30m")              

            job_ids.append(job.id)
            
        return job_ids