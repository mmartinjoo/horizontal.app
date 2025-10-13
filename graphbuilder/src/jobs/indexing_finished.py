import logging
from src.factories import create_graph_client

def indexing_finished(tenant_id: str):
    logging.warning(f"++++++++ INDEXING FINISHED for tenant {tenant_id} ++++++++")
    graph_client = create_graph_client(tenant_id=tenant_id)
    with graph_client.session() as session:
        session.run("STORAGE MODE IN_MEMORY_TRANSACTIONAL")