from typing import Any, List

from llama_index.core.bridge.pydantic import PrivateAttr
from llama_index.core.embeddings import BaseEmbedding
from openai import OpenAI
import os
import sys


class FireworksEmbeddor(BaseEmbedding):
    _client: OpenAI
    
    def __init__(
        self,
        **kwargs: Any,
    ) -> None:
        super().__init__(**kwargs)        
        self._client = OpenAI(
            base_url="https://api.fireworks.ai/inference/v1",
            api_key=os.getenv("FIREWORKS_API_KEY"),
        )

    @classmethod
    def class_name(cls) -> str:
        return "fireworks"

    async def _aget_query_embedding(self, query: str) -> List[float]:
        return self._get_query_embedding(query)

    async def _aget_text_embedding(self, text: str) -> List[float]:
        return self._get_text_embedding(text)

    def _get_query_embedding(self, query: str) -> List[float]:
        response = self._client.embeddings.create(
            model="nomic-ai/nomic-embed-text-v1.5",
            input=f"search_query: {query}",
        )
        return response.data[0].embedding

    def _get_text_embedding(self, text: str) -> List[float]:
        response = self._client.embeddings.create(
            model="nomic-ai/nomic-embed-text-v1.5",
            input=f"search_document: {text}",
        )        
        return response.data[0].embedding

    def _get_text_embeddings(self, texts: List[str]) -> List[List[float]]:
        embeddings = []
        for text in texts:
            response = self._client.embeddings.create(
                model="nomic-ai/nomic-embed-text-v1.5",
                input=f"search_document: {text}",
            )
            embeddings.append(response.data[0].embedding)
        return embeddings