#!/bin/bash

# Test script to verify race condition fix for ticket number generation
# This script simulates simultaneous ticket creation requests

echo "=== Testing Ticket Number Race Condition Fix ==="
echo ""
echo "This script will create 5 tickets simultaneously to verify unique ticket numbers"
echo ""

# Configuration
API_URL="https://cxsuite.telediant.com.my/api/v1"
TOKEN="YOUR_AUTH_TOKEN_HERE"  # Replace with valid token

# Test customer data
CUSTOMER_ID=1  # Replace with valid customer ID

# Function to create a ticket
create_ticket() {
    local ticket_num=$1
    echo "Creating ticket #$ticket_num..."

    curl -s "$API_URL/tickets" \
      -H "accept: application/json" \
      -H "authorization: Bearer $TOKEN" \
      -H "content-type: application/json" \
      -d "{
        \"customer_id\": $CUSTOMER_ID,
        \"priority\": \"P3\",
        \"department\": \"Support\",
        \"category\": \"General Inquiry\",
        \"subject\": \"Test Ticket #$ticket_num - Concurrent Creation Test\",
        \"description\": \"This is a test ticket to verify race condition fix\",
        \"channel\": \"phone\",
        \"urgency\": \"Medium\",
        \"impact\": \"Low\"
      }" | jq -r '.data.ticket_number'
}

# Run 5 concurrent ticket creations
echo "Starting concurrent ticket creation..."
echo ""

# Create tickets in parallel
ticket1=$(create_ticket 1 &)
ticket2=$(create_ticket 2 &)
ticket3=$(create_ticket 3 &)
ticket4=$(create_ticket 4 &)
ticket5=$(create_ticket 5 &)

# Wait for all background processes to complete
wait

echo ""
echo "=== Results ==="
echo "Ticket 1: $ticket1"
echo "Ticket 2: $ticket2"
echo "Ticket 3: $ticket3"
echo "Ticket 4: $ticket4"
echo "Ticket 5: $ticket5"
echo ""

# Check for duplicates
tickets=("$ticket1" "$ticket2" "$ticket3" "$ticket4" "$ticket5")
unique_count=$(printf '%s\n' "${tickets[@]}" | sort -u | wc -l)

if [ $unique_count -eq 5 ]; then
    echo "✅ SUCCESS: All ticket numbers are unique!"
else
    echo "❌ FAILURE: Found duplicate ticket numbers!"
    echo "Unique count: $unique_count out of 5"
fi
