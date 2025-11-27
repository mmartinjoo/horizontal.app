import os
import logging
from datetime import datetime
from dotenv import load_dotenv
from flask import Flask, request, jsonify
from src.graphbuilder import GraphBuilder
from src.factories import create_graph_client, create_llm
import redis

load_dotenv()

app = Flask(__name__)

@app.route("/health", methods=["GET"])
def health():
    """Basic health check endpoint for liveness probe"""
    return jsonify({
        "status": "healthy",
        "timestamp": datetime.utcnow().isoformat()
    }), 200

@app.route("/readiness", methods=["GET"])
def readiness():
    """Readiness check that verifies external dependencies"""
    try:
        # Check Redis connection
        redis_url = os.getenv("REDIS_URL")
        if redis_url:
            r = redis.from_url(redis_url, socket_connect_timeout=2)
            r.ping()

        return jsonify({
            "status": "ready",
            "timestamp": datetime.utcnow().isoformat(),
            "checks": {
                "redis": "ok"
            }
        }), 200
    except Exception as e:
        logging.error(f"Readiness check failed: {e}")
        return jsonify({
            "status": "not ready",
            "timestamp": datetime.utcnow().isoformat(),
            "error": str(e)
        }), 503

@app.route("/api/build", methods=["POST"])
def api_build_graph():
    try:
        body = request.get_json()
        if not body or "tenant_id" not in body:
            return jsonify({"error": "tenant_id is required"}), 400
        if "workflow_step_id" not in body:
            return jsonify({"error": "workflow_step_id is required"}), 400

        graph_builder = GraphBuilder(
            tenant_id=body["tenant_id"],
            workflow_step_id=body["workflow_step_id"],
        )
        graph_builder.build_graph_for_tenant()
        return jsonify({"status": "accepted"}), 202
    except Exception as e:
        logging.exception(e)    
        return jsonify({"success": False, "error": "something went wrong", "details": str(e.args[0])}), 500

if __name__ == '__main__':
    app.run(host="0.0.0.0", port="9998")
