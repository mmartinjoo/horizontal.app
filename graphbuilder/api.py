import os
import logging
from dotenv import load_dotenv
from flask import Flask, request, jsonify
from src.graphbuilder import GraphBuilder
from src.factories import create_graph_client

load_dotenv()
os.environ["OPENAI_API_KEY"] = os.getenv("FIREWORKS_API_KEY")

app = Flask(__name__)
    
@app.route("/api/build", methods=["POST"])
def api_build_graph():
    try:
        body = request.get_json()
        if not body or "tenant_id" not in body:
            return jsonify({"error": "tenant_id is required"}), 400

        graph_builder = GraphBuilder(body["tenant_id"])
        graph_builder.build_graph_for_tenant()
        return jsonify({"status": "accepted"}), 202
    except Exception as e:
        logging.exception(e)
        return jsonify({"success": False, "error": "somewthing went wrong"}), 500

@app.route("/api/test", methods=["GET"])
def api_test():
    body = request.get_json()
    if not body or "tenant_id" not in body:
        return jsonify({"error": "tenant_id is required"}), 400
    
    graph_client = create_graph_client(tenant_id=body["tenant_id"])
    with graph_client.session() as session:
        result = session.run("match (n) return n;")
        nodes = result.fetch(3)
        print(nodes)
        
    return jsonify({"status": "accepted"}), 202

if __name__ == '__main__':
    app.run(host="0.0.0.0", port="9998")
