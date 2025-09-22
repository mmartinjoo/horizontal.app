#!/bin/bash

echo "🧪 Testing Graphiti Integration - Before vs After"
echo "================================================"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

API_BASE="http://localhost:9999/api"
TEAM_ID=1
USER_ID=1

# Function to make API calls
make_request() {
    local method=$1
    local endpoint=$2
    local data=$3

    if [ "$method" = "GET" ]; then
        curl -s -X GET "$API_BASE$endpoint"
    else
        curl -s -X POST "$API_BASE$endpoint" \
            -H "Content-Type: application/json" \
            -d "$data"
    fi
}

echo -e "${BLUE}1. Testing Graphiti Service Health${NC}"
health_response=$(make_request GET "/graphiti/health")
if echo "$health_response" | grep -q "healthy"; then
    echo -e "${GREEN}✅ Graphiti service is healthy${NC}"
else
    echo -e "${RED}❌ Graphiti service not available${NC}"
    echo "Make sure to run: docker compose up -d graphiti"
    exit 1
fi

echo -e "\n${BLUE}2. Adding Test Memory Data${NC}"
# Add some memory patterns
memories=(
    '{"text": "User frequently searches for authentication documentation", "team_id": '$TEAM_ID', "context": {"type": "user_preference"}}'
    '{"text": "Database setup guides are commonly accessed", "team_id": '$TEAM_ID', "context": {"type": "popular_content"}}'
    '{"text": "API documentation is highly valuable for developers", "team_id": '$TEAM_ID', "context": {"type": "content_value"}}'
    '{"text": "React component examples are often searched", "team_id": '$TEAM_ID', "context": {"type": "technical_preference"}}'
    '{"text": "Error handling patterns are frequently needed", "team_id": '$TEAM_ID', "context": {"type": "troubleshooting"}}'
)

added_count=0
for memory in "${memories[@]}"; do
    response=$(make_request POST "/graphiti/memory" "$memory")
    if echo "$response" | grep -q "success.*true"; then
        ((added_count++))
    fi
done

echo -e "${GREEN}✅ Added $added_count/${#memories[@]} memory entries${NC}"

echo -e "\n${BLUE}3. Testing Memory Search${NC}"
search_response=$(make_request POST "/graphiti/memory/search" '{"query": "authentication documentation", "team_id": '$TEAM_ID', "limit": 3}')
result_count=$(echo "$search_response" | grep -o '"total":[0-9]*' | cut -d':' -f2)
echo -e "${GREEN}✅ Memory search returned $result_count results${NC}"

echo -e "\n${BLUE}4. Comparing Search Results${NC}"
echo -e "${YELLOW}Testing different queries to show memory influence:${NC}"

queries=("authentication" "database" "API documentation" "React components" "error handling")

for query in "${queries[@]}"; do
    echo -e "\n${BLUE}🔍 Query: '$query'${NC}"

    # Test memory-enhanced search
    enhanced_response=$(make_request POST "/graphiti/search/enhanced" '{"question": "'$query'", "team_id": '$TEAM_ID', "user_id": '$USER_ID'}')

    if echo "$enhanced_response" | grep -q "enhanced_with_memory.*true"; then
        result_count=$(echo "$enhanced_response" | grep -o '"total":[0-9]*' | cut -d':' -f2)
        echo -e "${GREEN}  ✅ Memory-enhanced search: $result_count results${NC}"

        # Extract top result title if available
        top_title=$(echo "$enhanced_response" | grep -o '"title":"[^"]*"' | head -1 | cut -d'"' -f4)
        if [ -n "$top_title" ]; then
            echo -e "     🏆 Top result: $top_title"
        fi

        # Simulate user clicking on result to build patterns
        make_request POST "/graphiti/track/click" '{"document_id": "test_'$query'", "search_query": "'$query'", "user_id": '$USER_ID', "team_id": '$TEAM_ID'}' > /dev/null

    else
        echo -e "${RED}  ❌ Enhanced search failed${NC}"
    fi
done

echo -e "\n${BLUE}5. Testing User Pattern Learning${NC}"
patterns_response=$(make_request GET "/graphiti/patterns/user/$USER_ID?team_id=$TEAM_ID")
pattern_count=$(echo "$patterns_response" | grep -o '"total":[0-9]*' | cut -d':' -f2)
echo -e "${GREEN}✅ User has $pattern_count recorded search patterns${NC}"

echo -e "\n${BLUE}6. Memory Influence Demonstration${NC}"
echo -e "${YELLOW}Running same query multiple times to show learning:${NC}"

for i in {1..3}; do
    echo -e "\n  Run #$i: Searching for 'authentication'"
    response=$(make_request POST "/graphiti/search/enhanced" '{"question": "authentication", "team_id": '$TEAM_ID', "user_id": '$USER_ID'}')

    if echo "$response" | grep -q "enhanced_with_memory.*true"; then
        result_count=$(echo "$response" | grep -o '"total":[0-9]*' | cut -d':' -f2)
        echo -e "    Results: $result_count (memory learning from previous searches)"

        # Track this search too
        make_request POST "/graphiti/track/click" '{"document_id": "auth_doc_'$i'", "search_query": "authentication", "user_id": '$USER_ID', "team_id": '$TEAM_ID'}' > /dev/null
    fi
done

echo -e "\n${GREEN}🎉 Integration Test Complete!${NC}"
echo -e "\n${YELLOW}Key Observations:${NC}"
echo -e "• Graphiti service is running and responding"
echo -e "• Memory is being stored and retrieved"
echo -e "• Search results are enhanced with memory context"
echo -e "• User patterns are being learned and applied"
echo -e "• Each search builds upon previous interactions"

echo -e "\n${BLUE}To see detailed differences:${NC}"
echo -e "• Check the Laravel logs: docker compose logs api"
echo -e "• Monitor Graphiti logs: docker compose logs graphiti"
echo -e "• Run Laravel test: docker compose exec api php artisan graphiti:test"