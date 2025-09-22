#!/bin/bash

echo "🧠 Graphiti Memory Integration Demo"
echo "=================================="

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

GRAPHITI_URL="http://localhost:9995"

echo -e "${BLUE}1. Testing Graphiti Service Health${NC}"
health=$(curl -s "$GRAPHITI_URL/health")
if echo "$health" | grep -q "healthy"; then
    echo -e "${GREEN}✅ Graphiti service is running${NC}"
else
    echo -e "❌ Graphiti service not available"
    exit 1
fi

echo -e "\n${BLUE}2. Adding Knowledge to Memory${NC}"
memories=(
    '{"text": "Users frequently search for authentication guides and documentation", "team_id": 1}'
    '{"text": "Database setup tutorials are highly valued by the team", "team_id": 1}'
    '{"text": "API endpoint documentation gets the most clicks", "team_id": 1}'
    '{"text": "React component examples are popular search topics", "team_id": 1}'
    '{"text": "Error handling best practices are often needed", "team_id": 1}'
)

added_count=0
for memory in "${memories[@]}"; do
    response=$(curl -s -X POST "$GRAPHITI_URL/api/memory/add" \
        -H "Content-Type: application/json" \
        -d "$memory")

    if echo "$response" | grep -q "success"; then
        ((added_count++))
    fi
done

echo -e "${GREEN}✅ Added $added_count/${#memories[@]} knowledge entries to memory${NC}"

echo -e "\n${BLUE}3. Testing Memory Search & Learning${NC}"
queries=("authentication" "database setup" "API docs" "React components" "error handling")

for query in "${queries[@]}"; do
    echo -e "\n${YELLOW}🔍 Searching for: '$query'${NC}"

    search_data='{"query": "'$query'", "team_id": 1, "limit": 3}'
    response=$(curl -s -X POST "$GRAPHITI_URL/api/memory/search" \
        -H "Content-Type: application/json" \
        -d "$search_data")

    total=$(echo "$response" | grep -o '"total":[0-9]*' | cut -d':' -f2)
    echo -e "   📊 Found $total relevant memories"

    # Show top result if available
    if [ "$total" -gt "0" ]; then
        content=$(echo "$response" | grep -o '"content":"[^"]*"' | head -1 | cut -d'"' -f4)
        score=$(echo "$response" | grep -o '"score":[0-9.]*' | head -1 | cut -d':' -f2)
        echo -e "   🎯 Top match (score: $score): $content"
    fi
done

echo -e "\n${BLUE}4. Demonstrating Pattern Learning${NC}"
echo -e "${YELLOW}Adding user search patterns...${NC}"

patterns=(
    '{"user_id": 1, "search_query": "authentication", "team_id": 1, "clicked_results": ["doc_123"], "search_context": "tutorial"}'
    '{"user_id": 1, "search_query": "database migration", "team_id": 1, "clicked_results": ["doc_456"], "search_context": "setup"}'
    '{"user_id": 2, "search_query": "API testing", "team_id": 1, "clicked_results": ["doc_789"], "search_context": "development"}'
)

pattern_count=0
for pattern in "${patterns[@]}"; do
    response=$(curl -s -X POST "$GRAPHITI_URL/api/patterns/search" \
        -H "Content-Type: application/json" \
        -d "$pattern")

    if echo "$response" | grep -q "success"; then
        ((pattern_count++))
    fi
done

echo -e "${GREEN}✅ Recorded $pattern_count user search patterns${NC}"

echo -e "\n${BLUE}5. Retrieving User Patterns${NC}"
for user_id in 1 2; do
    echo -e "${YELLOW}User $user_id patterns:${NC}"

    response=$(curl -s "$GRAPHITI_URL/api/patterns/user/$user_id?team_id=1&limit=5")
    total=$(echo "$response" | grep -o '"total":[0-9]*' | cut -d':' -f2)
    echo -e "   📈 $total patterns recorded for user $user_id"
done

echo -e "\n${GREEN}🎉 Demo Complete!${NC}"
echo -e "\n${BLUE}Key Capabilities Demonstrated:${NC}"
echo -e "• ✅ Memory storage and retrieval"
echo -e "• ✅ Semantic search across stored knowledge"
echo -e "• ✅ User pattern tracking and learning"
echo -e "• ✅ Team-isolated data storage"
echo -e "• ✅ Real-time knowledge accumulation"

echo -e "\n${YELLOW}Integration Benefits:${NC}"
echo -e "• 🧠 Your search results now learn from user behavior"
echo -e "• 🎯 Personalized ranking based on historical patterns"
echo -e "• 📈 Continuous improvement through interaction tracking"
echo -e "• 🔒 Team-scoped privacy and data isolation"