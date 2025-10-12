import os
import logging
from typing import Callable, List
from llama_index.core.indices.property_graph import SimpleLLMPathExtractor
from llama_index.core import PropertyGraphIndex, Document
from llama_index.readers.database import DatabaseReader
from llama_index.core.embeddings import BaseEmbedding
from llama_index.llms.fireworks import Fireworks
from llama_index.graph_stores.memgraph import MemgraphPropertyGraphStore
from psycopg2.extensions import cursor as Cursor

def index_batch(type: str,                
                reader: DatabaseReader,
                limit: int,
                load_fn: Callable[[DatabaseReader, int], List[Document]],
                update_fn: Callable[[List[Document], Cursor], any],
                llm: Fireworks,
                embed_model: BaseEmbedding,
                graph_store: MemgraphPropertyGraphStore,
                cursor: Cursor,
                num_of_batches: int,
                batch_serial: int,
                tenant_id: str):
    
    logging.info(f"Processing batch {batch_serial}/{num_of_batches}... for tenant {tenant_id}")
    logging.info(f"Loading {type}s...")
    documents = load_fn(reader=reader, limit=limit)
    
    if len(documents) == 0:
        logging.info(f"All {type}s are processed")
        return

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
    update_fn(documents, cursor)
    logging.info(f"{type}s updated")
    
    logging.info(f"batch {batch_serial}/{num_of_batches} processed")