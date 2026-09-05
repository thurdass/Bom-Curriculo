"""Generic AI provider backed by LangChain's ``init_chat_model``.

One class handles every supported provider (Groq, Gemini, DeepSeek, OpenAI,
Ollama) by delegating the actual HTTP/SDK work to LangChain's chat-model
integrations and their built-in structured-output support, instead of each
provider hand-rolling HTTP requests and JSON parsing.
"""

from langchain.chat_models import init_chat_model
from pydantic import BaseModel

from app.providers.base import AIProvider, AIProviderError
from app.providers.error_mapping import map_provider_error

_MODEL_PROVIDERS = {
    "groq": "groq",
    "gemini": "google_genai",
    "deepseek": "deepseek",
    "openai": "openai",
    "ollama": "ollama",
}


class LangChainProvider(AIProvider):
    """AI provider whose transport is entirely delegated to LangChain."""

    def __init__(
        self,
        name: str,
        model: str,
        api_key: str = "",
        base_url: str | None = None,
        timeout: float = 120.0,
        output_language: str = "pt-BR",
        reasoning: bool | None = None,
        max_output_tokens: int | None = None,
    ) -> None:
        if name not in _MODEL_PROVIDERS:
            raise AIProviderError(f"Unsupported provider '{name}'.", category="invalid_model")
        if name != "ollama" and not api_key.strip():
            raise AIProviderError(f"The {name} provider has no API key configured.", category="missing_api_key")

        self.name = name
        self.model = model
        self.output_language = output_language
        self.reasoning = reasoning
        self.max_output_tokens = max_output_tokens
        self._chat_model = init_chat_model(
            model,
            model_provider=_MODEL_PROVIDERS[name],
            **self._transport_kwargs(name, api_key, base_url, timeout),
        )

    @staticmethod
    def _transport_kwargs(name: str, api_key: str, base_url: str | None, timeout: float) -> dict:
        if name == "ollama":
            return {"base_url": base_url, "client_kwargs": {"timeout": timeout}}
        return {"api_key": api_key, "timeout": timeout}

    async def run_structured(
        self, prompt: str, schema: type[BaseModel], temperature: float = 0.2
    ) -> BaseModel | None:
        if self.name == "ollama":
            ollama_options = {"temperature": temperature}
            if self.max_output_tokens is not None:
                ollama_options["num_predict"] = self.max_output_tokens
            # Apply Ollama invocation options after structured output. ChatOllama's
            # with_structured_output() creates its own binding for `format`; applying
            # it second would replace reasoning/options instead of combining them.
            structured_model = self._chat_model.with_structured_output(schema).bind(
                reasoning=False if self.reasoning is None else self.reasoning,
                options=ollama_options,
            )
        else:
            structured_model = self._chat_model.bind(
                temperature=temperature
            ).with_structured_output(schema)
        try:
            result = await structured_model.ainvoke(prompt)
        except Exception as error:
            raise map_provider_error(self.name, error) from error
        if result is None:
            raise AIProviderError(f"{self.name.capitalize()} returned an empty response.", category="empty_response")
        return result
