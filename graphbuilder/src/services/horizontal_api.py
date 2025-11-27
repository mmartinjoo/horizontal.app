import os
import requests
from typing import Dict, List

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
    domains = data["domains"]

    # Production case: tenant has single domain, use it directly
    if len(domains) == 1:
        return domains[0]

    # Local dev case: tenant has multiple domains, match against HORIZONTAL_API_URL hostname
    # (e.g., selects "tenant.nginx" for docker-internal communication)
    hostname = _extract_hostname(os.getenv('HORIZONTAL_API_URL'))
    for domain in domains:
        if hostname in domain:
            return domain

    raise RuntimeError(f"domain cannot be determined from {len(domains)} domains")

def get_llm_provider(tenant_id: str) -> str:
    # Use base API URL for this initial request (no tenant context needed)
    base_url = _get_base_api_url()
    resp = requests.get(f"{base_url}/api/tenants/{tenant_id}")
    if resp.status_code != 200:
        raise RuntimeError("Failed to get tenant from API")

    data = resp.json()
    if "llm_provider" not in data:
        raise RuntimeError("unable to fetch LLM provider")
    
    return data["llm_provider"]

def create_workflow_bucket(tenant_id: str, data: Dict) -> Dict:
    url_data = _create_tenant_request_data(tenant_id=tenant_id, path="api/orchestrator/workflows/buckets")
    resp = requests.post(url_data["url"], json=data, headers=url_data["headers"])
    if resp.status_code != 201:
        raise RuntimeError(f"Failed to create bucket: {resp.status_code}")

    response_data = resp.json()
    return response_data['bucket']

def add_bucket_items(tenant_id: str, bucket_id: str, type: str, ids: List[int]) -> List[int]:
    data = {
        "ids": ids,
        "type": type,
        "bucket_id": bucket_id,
    }
    
    url_data = _create_tenant_request_data(tenant_id=tenant_id, path="api/orchestrator/workflows/buckets/items")
    resp = requests.post(url_data["url"], json=data, headers=url_data["headers"])
    if resp.status_code != 201:
        raise RuntimeError(f"Failed to add items: {resp.status_code}")
    
    response_data = resp.json()
    return response_data['item_ids']

def mark_bucket_items_as_processing(tenant_id: str, bucket_item_ids: int, job_id: str) -> None:
    data = {
        "bucket_item_ids": bucket_item_ids,
        "job_id": job_id,
    }
    
    url_data = _create_tenant_request_data(tenant_id=tenant_id, path="api/orchestrator/workflows/buckets/items/processing")
    resp = requests.post(url_data["url"], json=data, headers=url_data["headers"])
    if resp.status_code != 204:
        raise RuntimeError(f"Failed to mark item as processing: {resp.status_code}")
    
def mark_bucket_items_as_completed(tenant_id: str, bucket_item_ids: int) -> None:
    data = {
        "bucket_item_ids": bucket_item_ids,
    }
    
    url_data = _create_tenant_request_data(tenant_id=tenant_id, path="api/orchestrator/workflows/buckets/items/completed")
    resp = requests.post(url_data["url"], json=data, headers=url_data["headers"])
    if resp.status_code != 204:
        raise RuntimeError(f"Failed to mark items as completed: {resp.status_code}")
    
def mark_bucket_items_as_failed(tenant_id: str, bucket_item_ids: int, exception: Exception) -> None:
    data = {
        "bucket_item_ids": bucket_item_ids,
        "error_message": str(exception),
    }
    
    url_data = _create_tenant_request_data(tenant_id=tenant_id, path="api/orchestrator/workflows/buckets/items/failed")
    resp = requests.post(url_data["url"], json=data, headers=url_data["headers"])
    if resp.status_code != 204:
        raise RuntimeError(f"Failed to mark items as failed: {resp.status_code}")

def _create_tenant_request_data(tenant_id: str, path: str) -> Dict[str, any]:
    tenant_domain = get_tenant_domain(tenant_id)
    base_url = _get_base_api_url()
    headers = {
        "Host": tenant_domain,
        "Accepts": "application/json",
        "Content-Type": "application/json"
    }
    data = {
        "url": f"{base_url}/{path}",
        "headers": headers,
    }
    return data

def _get_base_api_url() -> str:
    return os.getenv('HORIZONTAL_API_URL')

def _extract_hostname(url: str) -> str:
    # Remove the protocol (http:// or https://)
    if "://" in url:
        url = url.split("://")[1]

    # Split the URL by ':' to separate the hostname from the port
    hostname = url.split(':')[0]

    return hostname