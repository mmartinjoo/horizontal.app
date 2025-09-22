#!/bin/bash

echo "🧠 Viewing Stored Graphiti Memories"
echo "=================================="

GRAPHITI_URL="http://localhost:9995"

echo "📋 All stored memories for Team 1:"
echo

# Search with empty query to get all memories
response=$(curl -s -X POST "$GRAPHITI_URL/api/memory/search" \
    -H "Content-Type: application/json" \
    -d '{"query": "", "team_id": 1, "limit": 20}')

# Parse and display results
echo "$response" | python3 -c "
import json
import sys

try:
    data = json.load(sys.stdin)
    results = data.get('results', [])
    total = data.get('total', 0)

    print(f'Found {total} total memories:\n')

    for i, result in enumerate(results, 1):
        content = result.get('content', 'No content')
        score = result.get('score', 0)
        episode_id = result.get('episode_id', 'Unknown')
        timestamp = result.get('timestamp', 'Unknown')

        print(f'{i}. Episode: {episode_id}')
        print(f'   Content: {content}')
        print(f'   Score: {score}')
        print(f'   Time: {timestamp}')
        print()

except Exception as e:
    print(f'Error parsing response: {e}')
    print('Raw response:')
    print(sys.stdin.read())
"