import os
import asyncio
from fastapi import FastAPI, HTTPException, Request
from graphbuilder import GraphBuilder

app = FastAPI()
graph_builder = GraphBuilder()
    
@app.post("/api/build", status_code=202)
async def api_build_graph(req: Request):
    try:
        body = await req.json()
        asyncio.create_task(graph_builder.build_graph_for_tenant(body["tenant_id"]))
        return {"status": "accepted"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == '__main__':
    import uvicorn
    # Enable reload for development - set reload=False for production
    reload_mode = os.getenv('HOT_RELOAD_ENABLED', 'true').lower() == 'true'
    uvicorn.run("api:app", host='0.0.0.0', port=9998, reload=reload_mode)
