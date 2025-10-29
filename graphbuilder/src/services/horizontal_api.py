import os
import requests
from typing import Dict

cache = {}

def get_graph_db_connection_info(tenant_id: str):
    if tenant_id in cache:
        return cache[tenant_id]

    # Use base API URL but set Host header for tenant identification
    base_url = _get_base_api_url()

    resp = requests.get(f"{base_url}/api/tenants/{tenant_id}")
    if resp.status_code != 200:
        raise RuntimeError("Failed to get tenant connection info")

    data = resp.json()
    cache[tenant_id] = {
        "url": f"bolt://{data['graph_db_connection']['host']}:{data['graph_db_connection']['port']}",
        "user": data["graph_db_connection"]["user"],
        "password": data["graph_db_connection"]["password"],
    }
    return cache[tenant_id]

def get_tenant_domain(tenant_id: str) -> str:
    # Use base API URL for this initial request (no tenant context needed)
    base_url = _get_base_api_url()
    resp = requests.get(f"{base_url}/api/tenants/{tenant_id}")
    if resp.status_code != 200:
        raise RuntimeError("Failed to get tenant from API")

    data = resp.json()
    hostname = _extract_hostname(os.getenv('HORIZONTAL_API_URL'))
    for domain in data["domains"]:
        if f"{domain}".find(hostname) != -1:
            return domain

    raise RuntimeError("domain cannot be determined")

def create_workflow_bucket(tenant_id: str, data: Dict) -> int:
    tenant_domain = get_tenant_domain(tenant_id)
    base_url = _get_base_api_url()
    
    headers = {
        "Host": tenant_domain,
    }

    resp = requests.post(f"{base_url}/api/workflows/buckets", json=data, headers=headers)
    if resp.status_code != 201:
        raise RuntimeError(f"Failed to create bucket: {resp.status_code}")

    response_data = resp.json()
    return response_data['bucket']

def _get_base_api_url() -> str:
    return os.getenv('HORIZONTAL_API_URL')

def _extract_hostname(url: str) -> str:
    # Remove the protocol (http:// or https://)
    if "://" in url:
        url = url.split("://")[1]

    # Split the URL by ':' to separate the hostname from the port
    hostname = url.split(':')[0]

    return hostname