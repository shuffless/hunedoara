#!/bin/bash
#
# Test script to send sample HL7 and XML messages to Patient Hub.
# Usage: bash test_send.sh [http|tcp]
#
# Defaults to HTTP mode.

MODE=${1:-http}
SERVER="localhost"
HTTP_URL="http://$SERVER/patient-hub/api/receive.php"
TCP_PORT=5500

echo "=== Patient Hub Test Sender ==="
echo "Mode: $MODE"
echo ""

# --- Sample HL7 Message ---
HL7_MSG='MSH|^~\&|HospitalA|FacilityA|PatientHub|PatientHub|20240315120000||ADT^A01|MSG001|P|2.3
EVN|A01|20240315120000
PID|1||PAT001||Doe^John^M||19800115|M|||123 Main St^^Cityville^ST^12345||555-1234||||123-45-6789
PV1|1|I|ICU^^^MainHosp||||Doc^Smith^J||||||||||||V001
DG1|1||J18.9|Pneumonia
NK1|1|Doe^Jane|Spouse||555-5678
IN1|1|INS001||Blue Cross||||GRP01
AL1|1||Penicillin'

# --- Sample XML Message ---
XML_MSG='<?xml version="1.0" encoding="UTF-8"?>
<PatientMessage>
    <Header>
        <MessageType>ADT</MessageType>
        <SendingFacility>Hospital B</SendingFacility>
        <MessageDateTime>20240315130000</MessageDateTime>
    </Header>
    <Patient>
        <PatientID>PAT002</PatientID>
        <FirstName>Jane</FirstName>
        <LastName>Smith</LastName>
        <MiddleName>A</MiddleName>
        <DateOfBirth>19900522</DateOfBirth>
        <Sex>F</Sex>
        <Address>456 Oak Ave, Townsville, ST 67890</Address>
        <Phone>555-9876</Phone>
        <SSN>987-65-4321</SSN>
    </Patient>
    <Visit>
        <PatientClass>I</PatientClass>
        <AttendingDoctor>Dr. Johnson</AttendingDoctor>
        <ReferringDoctor>Dr. Williams</ReferringDoctor>
        <VisitNumber>V002</VisitNumber>
        <AdmitDateTime>20240315130000</AdmitDateTime>
    </Visit>
    <Diagnosis>
        <Code>I21.0</Code>
        <Description>Acute myocardial infarction</Description>
    </Diagnosis>
    <NextOfKin>
        <Name>Bob Smith</Name>
        <Relationship>Brother</Relationship>
        <Phone>555-4321</Phone>
    </NextOfKin>
</PatientMessage>'

if [ "$MODE" == "http" ]; then
    echo "--- Sending HL7 via HTTP ---"
    curl -s -X POST "$HTTP_URL" \
        -H "Content-Type: application/hl7-v2" \
        -d "$HL7_MSG" | python3 -m json.tool 2>/dev/null || curl -s -X POST "$HTTP_URL" -d "$HL7_MSG"
    echo ""
    echo ""

    echo "--- Sending XML via HTTP ---"
    curl -s -X POST "$HTTP_URL" \
        -H "Content-Type: application/xml" \
        -d "$XML_MSG" | python3 -m json.tool 2>/dev/null || curl -s -X POST "$HTTP_URL" -d "$XML_MSG"
    echo ""

elif [ "$MODE" == "tcp" ]; then
    echo "--- Sending HL7 via TCP (MLLP) ---"
    # Send with MLLP framing: 0x0B + message + 0x1C + 0x0D
    printf '\x0B%s\x1C\x0D' "$HL7_MSG" | nc -w 5 $SERVER $TCP_PORT
    echo ""
    echo ""

    echo "--- Sending XML via TCP ---"
    printf '%s' "$XML_MSG" | nc -w 5 $SERVER $TCP_PORT
    echo ""
else
    echo "Usage: bash test_send.sh [http|tcp]"
fi

echo ""
echo "Done. Check the dashboard at http://$SERVER/patient-hub/dashboard.php"
