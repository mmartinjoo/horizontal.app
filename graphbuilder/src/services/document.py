import logging
from typing import List
from psycopg2.extensions import cursor as Cursor
from llama_index.core import Document
from llama_index.readers.database import DatabaseReader

def get_document_chunk_batch(reader: DatabaseReader, ids: List[int]) -> List[Document]:
    ids_str = ",".join([str(id) for id in ids])
    documents = reader.load_data(
        query=f"""
            select
                document_chunks.id as document_chunk_id,
                documents.title as title,
                document_chunks.body as body,
                documents.source_type as source_type,
                documents.source_url as source_url,
                documents.id as source_document_id,
                'document' as document_type
            from document_chunks
            inner join documents on documents.id = document_chunks.document_id
            where document_chunks.id in ({ids_str})
            order by document_chunks.id        
        """,
        metadata_cols=[
            "title", "source_type", "source_url", "document_chunk_id", "source_document_id", "document_type",
        ],
        excluded_text_cols=[
            "source_type", "source_url", "document_chunk_id", "source_document_id", "document_type",
        ],
    )
    
    # Original comments are copied to custom documents because the LLM also received
    # the metadata and created graph nodes for thing like "source_url" etc
    # If `excluded_embed_metadata_keys` and `exluded_llm_metadata_keys` are set in the `load_data` call it just doesn't work somehow
    transformed_documents = []
    for document in documents:
        doc = Document(
            text=document.get_content(),
            metadata=document.metadata,
            excluded_llm_metadata_keys=["source_type", "source_url", "document_chunk_id", "source_document_id", "document_type"],
            excluded_embed_metadata_keys=["source_url", "document_chunk_id", "source_document_id", "document_type"],
        )
        transformed_documents.append(doc)
        
    return transformed_documents

def get_comment_batch(reader: DatabaseReader, ids: List[int]) -> List[Document]:    
    ids_str = ",".join([str(id) for id in ids])
    comments = reader.load_data(
        query=f"""
            select
                document_comments.id as comment_id,
                document_comments.body as body,
                documents.source_type as source_type,
                documents.source_url as source_url,
                documents.id as parent_document_id,
                'comment' as document_type
            from document_comments
            inner join documents on documents.id = document_comments.document_id
            where document_comments.id in ({ids_str})
            order by document_comments.id            
        """,
        metadata_cols=[
            "source_type", "source_url", "comment_id", "parent_document_id", "document_type",
        ],
        excluded_text_cols=[
            "source_type", "source_url", "comment_id", "parent_document_id", "document_type",
        ],
    )

    # Original comments are copied to custom documents because the LLM also received
    # the metadata and created graph nodes for thing like "source_url" etc
    # I didn't find a better solution
    transformed_comments = []
    for comment in comments:
        doc = Document(
            text=comment.get_content(),
            metadata=comment.metadata,
            excluded_llm_metadata_keys=["source_type", "source_url", "comment_id", "parent_document_id", "document_type"],
            excluded_embed_metadata_keys=["comment_id", "parent_document_id"],
        )
        transformed_comments.append(doc)

    return transformed_comments

def count_document_chunks(cursor: Cursor) -> int:
    cursor.execute("select count(*) from document_chunks")
    return cursor.fetchone()[0]

def count_comments(cursor: Cursor) -> int:
    cursor.execute("select count(*) from document_comments")
    return cursor.fetchone()[0]

def get_next_batch(cursor: Cursor, table: str, limit: int, offset: int) -> List[int]:
    cursor.execute(f"""
                   select id 
                   from {table} 
                   order by id
                   limit {limit}
                   offset {offset}
                   """)

    return [row[0] for row in cursor.fetchall()]