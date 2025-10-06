import json
import asyncio
from graphbuilder import GraphBuilder


def handler(event, context):
    """
    AWS Lambda handler function for building knowledge graphs.

    Expected event format:
    {
        "tenant_id": "string"
    }
    """
    try:
        # Parse the input
        if 'body' in event:
            # API Gateway event format
            body = json.loads(event['body']) if isinstance(event['body'], str) else event['body']
        else:
            # Direct invocation format
            body = event

        tenant_id = body.get('tenant_id')
        if not tenant_id:
            return {
                'statusCode': 400,
                'body': json.dumps({
                    'error': 'tenant_id is required'
                })
            }

        # Create GraphBuilder instance and build graph
        graph_builder = GraphBuilder()

        # Run the async graph building process
        asyncio.run(graph_builder.build_graph_for_tenant(tenant_id))

        return {
            'statusCode': 200,
            'body': json.dumps({
                'status': 'completed',
                'tenant_id': tenant_id,
                'message': 'Graph built successfully'
            })
        }

    except Exception as e:
        return {
            'statusCode': 500,
            'body': json.dumps({
                'error': str(e),
                'message': 'Failed to build graph'
            })
        }