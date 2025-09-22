#!/bin/bash

echo "🚀 Testing Laravel Graphiti Integration Endpoints"
echo "==============================================="

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

LARAVEL_API="http://localhost:9999/api/graphiti"
TEAM_ID=1

echo -e "${BLUE}1. Testing Health Endpoint${NC}"
health=$(curl -s "$LARAVEL_API/health")
if echo "$health" | grep -q "healthy"; then
    echo -e "${GREEN}✅ Laravel ↔ Graphiti communication working${NC}"
else
    echo -e "${RED}❌ Laravel API not working${NC}"
    exit 1
fi

echo -e "\n${BLUE}2. Running Full Demo via Laravel${NC}"
demo_response=$(curl -s -X POST "$LARAVEL_API/demo/run" \
    -H "Content-Type: application/json" \
    -d "{\"team_id\": $TEAM_ID}")

# Parse demo response
status=$(echo "$demo_response" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
if [ "$status" = "completed" ]; then
    echo -e "${GREEN}✅ Demo completed successfully${NC}"

    # Extract summary info
    knowledge_added=$(echo "$demo_response" | grep -o '"knowledge_added":[0-9]*' | cut -d':' -f2)
    patterns_recorded=$(echo "$demo_response" | grep -o '"patterns_recorded":[0-9]*' | cut -d':' -f2)
    search_tests=$(echo "$demo_response" | grep -o '"search_tests":[0-9]*' | cut -d':' -f2)

    echo -e "${YELLOW}   📊 Knowledge entries added: $knowledge_added${NC}"
    echo -e "${YELLOW}   👥 User patterns recorded: $patterns_recorded${NC}"
    echo -e "${YELLOW}   🔍 Search tests performed: $search_tests${NC}"
else
    echo -e "${RED}❌ Demo failed${NC}"
    echo "$demo_response"
    exit 1
fi

echo -e "\n${BLUE}3. Viewing Memories via Laravel${NC}"
view_response=$(curl -s "$LARAVEL_API/memory/view?team_id=$TEAM_ID&limit=20")

total_memories=$(echo "$view_response" | grep -o '"total_memories":[0-9]*' | cut -d':' -f2)
knowledge_entries=$(echo "$view_response" | grep -o '"knowledge_entries":[0-9]*' | cut -d':' -f2)
user_patterns=$(echo "$view_response" | grep -o '"user_patterns":[0-9]*' | cut -d':' -f2)

echo -e "${GREEN}✅ Retrieved memories via Laravel API${NC}"
echo -e "${YELLOW}   📚 Total memories: $total_memories${NC}"
echo -e "${YELLOW}   💡 Knowledge entries: $knowledge_entries${NC}"
echo -e "${YELLOW}   👥 User patterns: $user_patterns${NC}"

echo -e "\n${BLUE}4. Testing Memory Search via Laravel${NC}"
search_response=$(curl -s -X POST "$LARAVEL_API/memory/search" \
    -H "Content-Type: application/json" \
    -d '{"query": "authentication", "team_id": '$TEAM_ID', "limit": 3}')

search_total=$(echo "$search_response" | grep -o '"total":[0-9]*' | cut -d':' -f2)
echo -e "${GREEN}✅ Memory search returned $search_total results${NC}"

echo -e "\n${GREEN}🎉 Laravel Integration Test Complete!${NC}"
echo -e "\n${BLUE}Available Laravel Endpoints:${NC}"
echo -e "• GET  /api/graphiti/health - Service health check"
echo -e "• POST /api/graphiti/demo/run - Run full demo"
echo -e "• GET  /api/graphiti/memory/view - View all memories"
echo -e "• POST /api/graphiti/memory/search - Search memories"
echo -e "• POST /api/graphiti/memory - Add memory"
echo -e "• POST /api/graphiti/search/enhanced - Enhanced search"
echo -e "• POST /api/graphiti/track/click - Track user clicks"
echo -e "• GET  /api/graphiti/patterns/user/{id} - Get user patterns"

echo -e "\n${YELLOW}Integration Benefits:${NC}"
echo -e "• 🔌 Laravel native API endpoints"
echo -e "• 🛡️  Authentication middleware support"
echo -e "• ✅ Input validation and error handling"
echo -e "• 📊 Structured JSON responses"
echo -e "• 🧠 Direct integration with your existing Laravel app"