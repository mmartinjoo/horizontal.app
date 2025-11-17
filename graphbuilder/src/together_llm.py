from llama_index.core.llms import CustomLLM, CompletionResponse, LLMMetadata
from llama_index.core.llms.callbacks import llm_completion_callback
from typing import Any, Optional
import requests


class TogetherLLM(CustomLLM):
    """Custom LLM implementation for Together AI."""
    
    api_key: str
    model_name: str = "moonshotai/Kimi-K2-Instruct-0905"
    api_base: str = "https://api.together.xyz/v1"
    temperature: float = 0.0
    max_tokens: int = 8192
    
    def __init__(
        self,
        api_key: str,
        model_name: str = "moonshotai/Kimi-K2-Instruct-0905",
        temperature: float = 0.0,
        max_tokens: int = 8192,
        **kwargs
    ):
        super().__init__(
            api_key=api_key,
            model_name=model_name,
            temperature=temperature,
            max_tokens=max_tokens,
            **kwargs
        )
    
    @property
    def metadata(self) -> LLMMetadata:
        """Get LLM metadata."""
        return LLMMetadata(
            context_window=16384,
            num_output=self.max_tokens,
            model_name=self.model_name,
        )
    
    @llm_completion_callback()
    def complete(self, prompt: str, **kwargs: Any) -> CompletionResponse:
        """Call Together API for completion."""
        
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
            "Accept": "application/json",
        }
        
        payload = {
            "model": self.model_name,
            "messages": [
                {"role": "user", "content": prompt}
            ],
            "temperature": kwargs.get("temperature", self.temperature),
            "max_tokens": 4096,
            "top_p": 1,
            "top_k": 40,
            "presence_penalty": 0,
            "frequency_penalty": 0,
            "stream": False,
        }
        
        response = requests.post(
            f"{self.api_base}/chat/completions",
            headers=headers,
            json=payload,
        )
        
        response.raise_for_status()
        result = response.json()
        
        text = result["choices"][0]["message"]["content"]
        
        return CompletionResponse(
            text=text,
            raw=result,
        )
    
    @llm_completion_callback()
    def stream_complete(self, prompt: str, **kwargs: Any):
        """Streaming not implemented."""
        raise NotImplementedError("Streaming is not implemented for this LLM")

