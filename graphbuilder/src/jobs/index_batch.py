import os
import logging
from typing import List
from rq import get_current_job
from llama_index.core.indices.property_graph import SimpleLLMPathExtractor
from llama_index.core import PropertyGraphIndex
from llama_index.core import Document
from src.factories import create_db_reader, create_graph_store, create_llm, create_embed_model, create_db_cursor
from src.services import get_comment_batch, get_document_chunk_batch, mark_document_chunks_as_processing, mark_document_chunks_as_processed, mark_comments_as_processed, mark_comments_as_processing

def index_batch(type: str,
                ids: List[int],
                tenant_id: str):
    
    logging.warning(f"Processing {type} batch (ids={ids}) for tenant {tenant_id}")
    
    if type == "document_chunks":
        get_batch_fn = get_document_chunk_batch
        mark_as_processing_fn = mark_document_chunks_as_processing
        mark_as_processed_fn = mark_document_chunks_as_processed
        
    if type == "document_comments":
        get_batch_fn = get_comment_batch
        mark_as_processing_fn = mark_comments_as_processing
        mark_as_processed_fn = mark_comments_as_processed
    
    reader = create_db_reader(tenant_id=tenant_id)
    cursor = create_db_cursor(tenant_id=tenant_id)
    llm = create_llm()
    embed_model = create_embed_model()
    documents = get_batch_fn(reader=reader, ids=ids)
    
    if len(documents) == 0:
        logging.warning(f"All {type} are processed")
        return
    
    job = get_current_job()
    mark_as_processing_fn(items=documents, cursor=cursor, job_id=job.id)

    logging.warning(f"Loaded {len(documents)}")

    logging.warning("Running LLM Path Extractor")
    kg_extractor = SimpleLLMPathExtractor(llm=llm,
                                          max_paths_per_chunk=20,
                                          num_workers=4)
    
    logging.warning("LLM Path Extractor finished...")
    
    show_progress = False
    if os.getenv("APP_ENV") == "development":
        show_progress = True
    
    logging.warning("Creating graph index...")    
    graph_store = create_graph_store(tenant_id=tenant_id)
    index = PropertyGraphIndex.from_documents(documents,
                                              llm=llm,
                                              embed_kg_nodes=True,
                                              embed_model=embed_model,
                                              kg_extractors=[kg_extractor],
                                              show_progress=show_progress,
                                              property_graph_store=graph_store)
    logging.warning("Graph index created")

    logging.warning(f"Inserting {len(documents)} {type}")
    for n, document in enumerate(documents):
        index.insert(document)
        logging.warning(f"Inserting to index: {n+1}/{len(documents)}")
    
    logging.warning(f"Updating {type}...")
    mark_as_processed_fn(items=documents, cursor=cursor)
    logging.warning(f"{type} updated")
    
    logging.warning(f"batch (ids={ids}) processed")