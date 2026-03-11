<?php
/**
 * XML Patient Data Parser
 *
 * Parses incoming XML patient messages into a normalized associative array.
 * Supports a flexible XML schema for patient data.
 *
 * Expected XML format example:
 * <PatientMessage>
 *   <Header>
 *     <MessageType>ADT</MessageType>
 *     <SendingFacility>Hospital A</SendingFacility>
 *     <MessageDateTime>20240101120000</MessageDateTime>
 *   </Header>
 *   <Patient>
 *     <PatientID>12345</PatientID>
 *     <FirstName>John</FirstName>
 *     <LastName>Doe</LastName>
 *     <MiddleName></MiddleName>
 *     <DateOfBirth>19800115</DateOfBirth>
 *     <Sex>M</Sex>
 *     <Address>123 Main St</Address>
 *     <Phone>555-1234</Phone>
 *     <SSN>123-45-6789</SSN>
 *   </Patient>
 *   <Visit>
 *     <PatientClass>I</PatientClass>
 *     <AssignedLocation></AssignedLocation>
 *     <AttendingDoctor>Dr. Smith</AttendingDoctor>
 *     <ReferringDoctor>Dr. Jones</ReferringDoctor>
 *     <VisitNumber>V001</VisitNumber>
 *     <AdmitDateTime>20240101120000</AdmitDateTime>
 *   </Visit>
 *   <Diagnosis>
 *     <Code>J18.9</Code>
 *     <Description>Pneumonia</Description>
 *   </Diagnosis>
 *   <NextOfKin>
 *     <Name>Jane Doe</Name>
 *     <Relationship>Spouse</Relationship>
 *     <Phone>555-5678</Phone>
 *   </NextOfKin>
 *   <Insurance>
 *     <PlanID>INS001</PlanID>
 *     <Company>Blue Cross</Company>
 *     <Group>GRP01</Group>
 *   </Insurance>
 * </PatientMessage>
 */

class XMLPatientParser {

    /**
     * Parse XML string and return patient data array.
     */
    public function parse($xmlString) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $errorMsg = 'XML parsing failed: ';
            foreach ($errors as $error) {
                $errorMsg .= trim($error->message) . '; ';
            }
            throw new Exception($errorMsg);
        }

        return $this->extractData($xml);
    }

    /**
     * Extract patient data from SimpleXML object.
     * Handles multiple possible XML structures by flattening recursively.
     */
    private function extractData($xml) {
        $data = [];
        $fieldMap = [
            // Header
            'messagetype' => 'message_type',
            'sendingfacility' => 'sending_facility',
            'receivingfacility' => 'receiving_facility',
            'messagedatetime' => 'message_datetime',
            'messagecontrolid' => 'message_control_id',
            // Patient
            'patientid' => 'patient_id',
            'firstname' => 'patient_first_name',
            'lastname' => 'patient_last_name',
            'middlename' => 'patient_middle_name',
            'dateofbirth' => 'date_of_birth',
            'sex' => 'sex',
            'gender' => 'sex',
            'address' => 'address',
            'phone' => 'phone',
            'ssn' => 'ssn',
            // Visit
            'patientclass' => 'patient_class',
            'assignedlocation' => 'assigned_location',
            'attendingdoctor' => 'attending_doctor',
            'referringdoctor' => 'referring_doctor',
            'visitnumber' => 'visit_number',
            'admitdatetime' => 'admit_datetime',
            // Diagnosis
            'code' => 'diagnosis_code',
            'description' => 'diagnosis_description',
            // Next of Kin
            'name' => 'next_of_kin_name',
            'relationship' => 'next_of_kin_relationship',
            // Insurance
            'planid' => 'insurance_plan_id',
            'company' => 'insurance_company',
            'group' => 'insurance_group',
        ];

        // Flatten all XML elements
        $flat = $this->flattenXml($xml);

        foreach ($flat as $key => $value) {
            $lowerKey = strtolower($key);
            if (isset($fieldMap[$lowerKey])) {
                $data[$fieldMap[$lowerKey]] = (string)$value;
            }
        }

        // Build patient_name from components
        $first = $data['patient_first_name'] ?? '';
        $last = $data['patient_last_name'] ?? '';
        if ($first || $last) {
            $data['patient_name'] = trim($first . ' ' . $last);
        }

        // Handle NextOfKin phone collision with patient phone
        // If we find phone under NextOfKin, map it separately
        if (isset($xml->NextOfKin->Phone)) {
            $data['next_of_kin_phone'] = (string)$xml->NextOfKin->Phone;
        }

        return $data;
    }

    /**
     * Recursively flatten XML into key => value pairs.
     * Leaf nodes only.
     */
    private function flattenXml($xml, $prefix = '') {
        $result = [];
        foreach ($xml->children() as $child) {
            $name = $child->getName();
            if ($child->count() > 0) {
                $result = array_merge($result, $this->flattenXml($child, $prefix . $name . '_'));
            } else {
                $value = (string)$child;
                if ($value !== '') {
                    $result[$name] = $value;
                }
            }
        }
        return $result;
    }
}
