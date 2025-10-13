import os
import logging
from rq import get_current_job
from llama_index.core.indices.property_graph import SimpleLLMPathExtractor
from llama_index.core import PropertyGraphIndex
from src.factories import create_db_reader, create_graph_store, create_llm, create_embed_model, create_db_cursor
from src.services import get_comment_batch, get_document_chunk_batch, mark_document_chunks_as_processing, mark_document_chunks_as_processed, mark_comments_as_processed, mark_comments_as_processing

def index_batch(type: str,
                limit: int,
                num_of_batches: int,
                batch_serial: int,
                tenant_id: str):
    
    logging.info(f"Processing batch {batch_serial}/{num_of_batches}... for tenant {tenant_id}")
    logging.info(f"Loading {type}s...")
    
    if type == "document_chunk":
        get_batch_fn = get_document_chunk_batch
        mark_as_processing_fn = mark_document_chunks_as_processing
        mark_as_processed_fn = mark_document_chunks_as_processed
        
    if type == "comment":
        get_batch_fn = get_comment_batch
        mark_as_processing_fn = mark_comments_as_processing
        mark_as_processed_fn = mark_comments_as_processed
    
    reader = create_db_reader(tenant_id=tenant_id)
    cursor = create_db_cursor(tenant_id=tenant_id)
    llm = create_llm()
    embed_model = create_embed_model()
    documents = get_batch_fn(reader=reader, limit=limit, offset=limit*(batch_serial-1))
    
    if len(documents) == 0:
        logging.info(f"All {type}s are processed")
        return
    
    job = get_current_job()
    mark_as_processing_fn(items=documents, cursor=cursor, job_id=job.id)

    logging.info(f"Loaded {len(documents)}")

    logging.info("Running LLM Path Extractor")
    kg_extractor = SimpleLLMPathExtractor(llm=llm,
                                          max_paths_per_chunk=20,
                                          num_workers=4)
    
    logging.info("LLM Path Extractor finished...")
    
    show_progress = False
    if os.getenv("APP_ENV") == "development":
        show_progress = True
    
    logging.info("Creating graph index...")    
    graph_store = create_graph_store(tenant_id=tenant_id)
    index = PropertyGraphIndex.from_documents(documents,
                                              llm=llm,
                                              embed_kg_nodes=True,
                                              embed_model=embed_model,
                                              kg_extractors=[kg_extractor],
                                              show_progress=show_progress,
                                              property_graph_store=graph_store)
    logging.info("Graph index created")

    logging.info(f"Inserting {len(documents)} {type}s")
    for n, document in enumerate(documents):
        index.insert(document)
        logging.info(f"Inserting to index: {n+1}/{len(documents)}")
    
    logging.info(f"Updating {type}s...")
    mark_as_processed_fn(items=documents, cursor=cursor)
    logging.info(f"{type}s updated")
    
    logging.info(f"batch {batch_serial}/{num_of_batches} processed")