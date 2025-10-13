import os
import requests

cache = {}

def get_graph_db_connection_info(tenant_id: str):
    if tenant_id in cache:
        return cache[tenant_id]
    
    resp = requests.get(f"{os.getenv('HORIZONTAL_API_URL')}/api/tenants/{tenant_id}")
    if resp.status_code != 200:
        raise RuntimeError("Failed to get tenant connection info")

    data = resp.json()
    cache[tenant_id] = {
        "url": f"bolt://{data['graph_db_connection']['host']}:{data['graph_db_connection']['port']}",
        "user": data["graph_db_connection"]["user"],
        "password": data["graph_db_connection"]["password"],
    }
    return cache[tenant_id]