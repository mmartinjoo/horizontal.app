import logging
from flask import Flask, request, jsonify
from src.graphbuilder import GraphBuilder

app = Flask(__name__)
graph_builder = GraphBuilder()
    
@app.route("/api/build", methods=["POST"])
def api_build_graph():
    body = request.get_json()
    if not body or "tenant_id" not in body:
        return jsonify({"error": "tenant_id is required"}), 400

    graph_builder.build_graph_for_tenant(body["tenant_id"])
    return jsonify({"status": "accepted"}), 202

if __name__ == '__main__':
    app.run(host="0.0.0.0", port="9998")
