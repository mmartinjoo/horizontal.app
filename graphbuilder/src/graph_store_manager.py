"""
Graph Store Manager - Thread-safe Singleton Pattern

This module provides a singleton manager for MemgraphPropertyGraphStore instances.
It ensures that each tenant gets a single, reusable graph store instance per worker process,
preventing race conditions during initialization and reducing connection overhead.

Key features:
- Thread-safe singleton pattern with locks
- Per-tenant graph store caching
- Lazy initialization
- Automatic cleanup on worker shutdown
"""

import threading
import logging
from typing import Dict, Optional
from llama_index.graph_stores.memgraph import MemgraphPropertyGraphStore
from src.services.horizontal_api import get_graph_db_connection_info
from tenacity import (
    retry,
    stop_after_attempt,
    wait_exponential,
    retry_if_exception_type,
    before_sleep_log
)
import neo4j

logger = logging.getLogger(__name__)


class GraphStoreManager:
    """
    Singleton manager for graph store instances.

    Ensures that each tenant has a single graph store instance per worker process,
    preventing concurrent initialization issues and improving performance.
    """

    _instance: Optional['GraphStoreManager'] = None
    _lock: threading.Lock = threading.Lock()

    def __new__(cls):
        if cls._instance is None:
            with cls._lock:
                # Double-check locking pattern
                if cls._instance is None:
                    cls._instance = super().__new__(cls)
        return cls._instance

    def __init__(self):
        # Only initialize once
        if not hasattr(self, '_initialized'):
            self._stores: Dict[str, MemgraphPropertyGraphStore] = {}
            self._store_locks: Dict[str, threading.Lock] = {}
            self._global_lock = threading.Lock()
            self._initialized = True

    @retry(
        retry=retry_if_exception_type(neo4j.exceptions.TransientError),
        stop=stop_after_attempt(5),
        wait=wait_exponential(multiplier=1, min=2, max=30),
        before_sleep=before_sleep_log(logger, logging.WARNING),
        reraise=True
    )
    def _create_graph_store_instance(self, tenant_id: str) -> MemgraphPropertyGraphStore:
        """
        Create a new graph store instance with retry logic for transient errors.

        This method handles transient Memgraph errors (like temporary lock conflicts)
        by retrying with exponential backoff.

        Args:
            tenant_id: The tenant identifier

        Returns:
            MemgraphPropertyGraphStore instance

        Raises:
            neo4j.exceptions.TransientError: If all retry attempts fail
        """
        connection_info = get_graph_db_connection_info(tenant_id)

        logger.info(f"Creating graph store instance for tenant {tenant_id}")

        graph_store = MemgraphPropertyGraphStore(
            url=connection_info["url"],
            username=connection_info["user"],
            password=connection_info["password"],
            database="memgraph",
            create_indexes=False,  # CRITICAL: Disable automatic index creation
            refresh_schema=True,
        )

        logger.info(f"Successfully created graph store instance for tenant {tenant_id}")
        return graph_store

    def get_graph_store(self, tenant_id: str) -> MemgraphPropertyGraphStore:
        """
        Get or create a graph store instance for the given tenant.

        This method is thread-safe and ensures only one instance per tenant
        is created within a worker process. It includes retry logic for
        handling transient Memgraph errors.

        Args:
            tenant_id: The tenant identifier

        Returns:
            MemgraphPropertyGraphStore instance for the tenant
        """
        # Fast path: check if store already exists (no lock needed for read)
        if tenant_id in self._stores:
            return self._stores[tenant_id]

        # Slow path: need to create the store (acquire locks)
        with self._global_lock:
            # Double-check: another thread might have created it while we waited
            if tenant_id in self._stores:
                return self._stores[tenant_id]

            # Create tenant-specific lock if it doesn't exist
            if tenant_id not in self._store_locks:
                self._store_locks[tenant_id] = threading.Lock()

            tenant_lock = self._store_locks[tenant_id]

        # Now acquire the tenant-specific lock to create the store
        with tenant_lock:
            # Triple-check: another thread might have created it while we waited
            if tenant_id in self._stores:
                return self._stores[tenant_id]

            # Create the graph store with retry logic for transient errors
            graph_store = self._create_graph_store_instance(tenant_id)

            # Cache the instance
            self._stores[tenant_id] = graph_store

            return graph_store

    def clear_store(self, tenant_id: str) -> None:
        """
        Remove a cached graph store instance.

        Useful for cleanup or when connection needs to be refreshed.

        Args:
            tenant_id: The tenant identifier
        """
        with self._global_lock:
            if tenant_id in self._stores:
                # Close connection if the store has a close method
                store = self._stores[tenant_id]
                if hasattr(store, 'close'):
                    store.close()

                del self._stores[tenant_id]

    def clear_all_stores(self) -> None:
        """
        Clear all cached graph store instances.

        Should be called during worker shutdown for cleanup.
        """
        with self._global_lock:
            for tenant_id in list(self._stores.keys()):
                self.clear_store(tenant_id)

            self._stores.clear()
            self._store_locks.clear()

    @classmethod
    def get_instance(cls) -> 'GraphStoreManager':
        """
        Get the singleton instance of GraphStoreManager.

        Returns:
            The singleton GraphStoreManager instance
        """
        if cls._instance is None:
            cls._instance = GraphStoreManager()
        return cls._instance


# Convenience function for easy access
def get_graph_store_for_tenant(tenant_id: str) -> MemgraphPropertyGraphStore:
    """
    Get a graph store instance for the given tenant.

    This is a convenience function that uses the singleton GraphStoreManager
    to provide a thread-safe, cached graph store instance.

    Args:
        tenant_id: The tenant identifier

    Returns:
        MemgraphPropertyGraphStore instance for the tenant
    """
    manager = GraphStoreManager.get_instance()
    return manager.get_graph_store(tenant_id)
