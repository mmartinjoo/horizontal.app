import os
import sys
import json
from llama_index.core.indices.property_graph import SimpleLLMPathExtractor
from llama_index.core import Settings, PropertyGraphIndex, SimpleDirectoryReader
from llama_index.llms.fireworks import Fireworks
from llama_index.graph_stores.memgraph import MemgraphPropertyGraphStore
from llama_index.readers.database import DatabaseReader
from llama_index.llms.openai import OpenAI
from llama_index.core.schema import Document
from dotenv import load_dotenv
from fireworks_embeddor import FireworksEmbeddor
from slugify import slugify

load_dotenv()

os.environ["OPENAI_API_KEY"] = os.getenv("FIREWORKS_API_KEY")
llm = Fireworks(
    api_key=os.getenv("FIREWORKS_API_KEY"),
    temperature=0,
    model=os.getenv("LLM_MODEL"),
    # api_base="https://api.fireworks.ai/inference/v1/chat/completions",
)
# os.environ["OPENAI_API_KEY"] = os.getenv("OPENAI_API_KEY")
# llm = OpenAI(
#     api_key=os.getenv("OPENAI_API_KEY"),
#     temperature=0,
#     model="gpt-4o"
# )

embed_model = FireworksEmbeddor()

Settings.llm = llm
Settings.embed_model = embed_model

reader = DatabaseReader(
    uri=os.getenv("DB_URI")
)

documents = reader.load_data(
    query="""
        select 
            document_chunks.id as id, 
            documents.title as title, 
            document_chunks.body as body, 
            documents.source_type as source_type,
            documents.source_url as source_url
        from document_chunks
        inner join documents on documents.id = document_chunks.document_id
    """,
    document_id=lambda row: f"{row['id']}",
    metadata_cols=[
        "title", "source_type", "source_url",
    ],
)

# for doc in documents:
#     name = doc.metadata["source_type"] + "_" + slugify(doc.metadata["title"])
#     path = os.path.join("input2", name)
#     with open(path, "w") as file:
#         file.write(doc.text)

# print(documents[0])
# sys.exit(-2)


# reader = SimpleDirectoryReader(input_dir="input2")
# documents = reader.load_data()

# print(documents[0])
# print(type(documents))

# sys.exit(-1)

def build_graph():
    graph_store = MemgraphPropertyGraphStore(
        password="",
        username="",
        url="bolt://127.0.0.1:7687"
    )
    kg_extractor = SimpleLLMPathExtractor(
        llm=llm, 
        max_paths_per_chunk=20, 
        num_workers=4,
    )
    PropertyGraphIndex.from_documents(
        documents,
        llm=llm,
        embed_kg_nodes=True,
        kg_extractors=[kg_extractor],
        show_progress=True,
        property_graph_store=graph_store,
    )
    
build_graph()