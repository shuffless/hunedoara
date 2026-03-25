# Patient Hub - Manual de Utilizare

## Cuprins

1. [Prezentare Generala](#1-prezentare-generala)
2. [Configurare si Instalare](#2-configurare-si-instalare)
3. [Autentificare si Gestionarea Utilizatorilor](#3-autentificare-si-gestionarea-utilizatorilor)
4. [Receptia Datelor Pacientilor](#4-receptia-datelor-pacientilor)
5. [Formatul HL7 v2.x](#5-formatul-hl7-v2x)
6. [Formatul XML](#6-formatul-xml)
7. [Tabloul de Bord (Dashboard)](#7-tabloul-de-bord-dashboard)
8. [Alocarea Paturilor](#8-alocarea-paturilor)
9. [Anularea Pacientilor](#9-anularea-pacientilor)
10. [Vizualizarea Cererilor](#10-vizualizarea-cererilor)
11. [Vizualizarea Mesajelor Trimise](#11-vizualizarea-mesajelor-trimise)
12. [Listener TCP (Serviciu de Ascultare)](#12-listener-tcp-serviciu-de-ascultare)
13. [Referinta API](#13-referinta-api)
14. [Structura Bazei de Date](#14-structura-bazei-de-date)
15. [Jurnalizarea Erorilor](#15-jurnalizarea-erorilor)
16. [Securitate](#16-securitate)

---

## 1. Prezentare Generala

**Patient Hub** este un sistem de gestionare a admiterilor spitalicesti care:

- Primeste cereri de admitere a pacientilor in format **HL7 v2.x** sau **XML**
- Gestioneaza alocarea paturilor catre pacientii aflati in asteptare
- Trimite mesaje de admitere (ADT^A01) sau anulare (ADT^A11) catre sistemele informatice de destinatie
- Ofera un **tablou de bord web** pentru personalul medical
- Inregistreaza toate tranzactiile si erorile intr-un jurnal complet

### Fluxuri Principale

| Flux | Descriere |
|------|-----------|
| Receptie date | Datele pacientului sosesc via HTTP POST sau socket TCP, sunt parsate si stocate |
| Alocare pat | Managerul aloca un pat disponibil unui pacient din coada de asteptare |
| Anulare pacient | Managerul anuleaza o cerere de admitere cu specificarea motivului |
| Gestionare utilizatori | Managerul adauga sau sterge conturi de utilizator |

---

## 2. Configurare si Instalare

### Cerinte de Sistem

- PHP 7.4+ cu extensiile PDO si MySQLi
- MySQL / MariaDB
- Server web Apache sau Nginx
- Bootstrap 5.3, jQuery 3.7, DataTables 1.13.6 (incluse)

### Fisierul de Configurare (`config.php`)

| Parametru | Valoare Implicita | Descriere |
|-----------|-------------------|-----------|
| `DB_HOST` | `localhost` | Adresa serverului de baze de date |
| `DB_NAME` | `patient_hub` | Numele bazei de date |
| `DB_USER` | `root` | Utilizatorul bazei de date |
| `DB_PASS` | *(gol)* | Parola bazei de date |
| `DEST_IP` | `192.168.20.80` | Adresa IP a sistemului de destinatie HL7 |
| `DEST_PORT` | `6600` | Portul sistemului de destinatie HL7 |
| `LISTEN_PORT` | `5500` | Portul de ascultare pentru conexiuni TCP |
| `APP_NAME` | `Patient Hub` | Numele aplicatiei |
| `TOTAL_BEDS` | `10` | Numarul total de paturi disponibile |
| `SESSION_TIMEOUT` | `3600` | Durata sesiunii in secunde (1 ora) |

### Instalarea Bazei de Date

Importati fisierul `db.sql` pentru a crea structura bazei de date:

```bash
mysql -u root -p < db.sql
```

Baza de date va fi creata cu:
- 10 paturi predefinite (Bed 1 - Bed 10)
- Un cont de manager implicit: utilizator `manager`, parola `hunedoara`

---

## 3. Autentificare si Gestionarea Utilizatorilor

### Autentificare

Sistemul foloseste **autentificare bazata pe sesiuni PHP**. La accesarea aplicatiei, utilizatorul este redirectionat catre pagina de login (`index.php`).

**Credentiale implicite:**
- Utilizator: `manager`
- Parola: `hunedoara`

### Roluri

| Rol | Drepturi |
|-----|----------|
| **Manager** (`is_manager = 1`) | Acces complet: alocare paturi, anulare pacienti, adaugare/stergere utilizatori |
| **Utilizator** (`is_manager = 0`) | Vizualizare tablou de bord, cereri, mesaje. NU poate sterge utilizatori |

### Gestionarea Utilizatorilor

Managerii pot gestiona utilizatorii din sectiunea **Gestionare Utilizatori** din tabloul de bord:

- **Adaugare utilizator:** Se completeaza numele de utilizator si parola (minim 4 caractere). Parola este criptata cu algoritm Bcrypt
- **Stergere utilizator:** Se apasa butonul de stergere de langa utilizator. Conturile de manager NU pot fi sterse
- **Deconectare:** Sesiunea este distrusa si utilizatorul este redirectionat la pagina de login

---

## 4. Receptia Datelor Pacientilor

Datele pacientilor pot fi primite prin doua canale:

### Canal 1: HTTP POST (`/api/receive.php`)

Endpoint public (nu necesita autentificare), destinat sistemelor externe.

**Cerere:**
```
POST /api/receive.php
Content-Type: application/hl7-v2 | application/xml | text/plain

[Corp: date brute HL7 sau XML]
```

**Raspuns de succes (200):**
```json
{
  "success": true,
  "request_id": 123,
  "patient_name": "Ion Popescu",
  "message": "Data received and processed successfully."
}
```

**Coduri de eroare:**
| Cod | Descriere |
|-----|-----------|
| 405 | Metoda HTTP nu este permisa (doar POST) |
| 400 | Corpul cererii este gol |
| 500 | Eroare de procesare interna |

### Canal 2: Socket TCP (`listener.php`)

Serviciu de ascultare pe portul 5500 (configurabil), accepta conexiuni TCP directe cu cadrul MLLP.

### Procesul de Receptie

1. Datele brute sunt inregistrate in tabela `raw_requests`
2. Formatul este detectat automat (HL7 sau XML)
3. Datele sunt parsate si stocate in tabela `patient_data` (model EAV)
4. Pacientul este adaugat in coada de asteptare (`pending_patients`) cu status `pending`

---

## 5. Formatul HL7 v2.x

### Segmente Suportate

| Segment | Descriere |
|---------|-----------|
| **MSH** | Antetul mesajului (tip, facilitate, data/ora) |
| **EVN** | Evenimentul declansator |
| **PID** | Identificarea pacientului (nume, CNP, adresa, telefon) |
| **PV1** | Informatii despre vizita (clasa, locatie, medic) |
| **DG1** | Diagnostic (cod, descriere) |
| **NK1** | Persoana de contact (nume, relatie, telefon) |
| **IN1** | Asigurare medicala (plan, companie, grup) |
| **AL1** | Alergii |
| **OBX** | Observatii clinice |

### Campuri Extrase

**Date pacient:**
- `patient_id` - Identificator unic
- `patient_name` - Nume complet
- `patient_first_name`, `patient_last_name`, `patient_middle_name`
- `date_of_birth` - Data nasterii
- `sex` - Sexul pacientului
- `address` - Adresa
- `phone` - Telefon
- `ssn` - CNP / Cod numeric personal

**Date vizita:**
- `patient_class` - Clasa pacientului (internat, ambulatoriu)
- `assigned_location` - Locatia atribuita
- `attending_doctor` - Medicul curant
- `referring_doctor` - Medicul care a facut trimiterea
- `visit_number` - Numar vizita
- `admit_datetime` - Data si ora admiterii

**Diagnostic:** `diagnosis_code`, `diagnosis_description`

**Contact:** `next_of_kin_name`, `next_of_kin_relationship`, `next_of_kin_phone`

**Asigurare:** `insurance_plan_id`, `insurance_company`, `insurance_group`

### Mesaje HL7 Generate

**ADT^A01 (Admitere):** Generat la alocarea unui pat. Contine segmentele MSH, EVN, PID, PV1 cu informatiile pacientului si patul alocat.

**ADT^A11 (Anulare):** Generat la anularea unei cereri. Contine segmentele MSH, EVN, PID, PV1 plus un segment custom **ZCR** cu motivul anularii.

### Protocolul MLLP

Mesajele sunt trimise folosind protocolul **MLLP** (Minimal Lower Layer Protocol):
- Prefix: `0x0B`
- Sufix: `0x1C` + `0x0D`
- Timeout conexiune: 10 secunde

---

## 6. Formatul XML

### Structura XML Acceptata

```xml
<PatientMessage>
  <Header>
    <MessageType>ADT^A01</MessageType>
    <SendingFacility>Spital Hunedoara</SendingFacility>
    <ReceivingFacility>Sistem Central</ReceivingFacility>
    <MessageDateTime>20240101120000</MessageDateTime>
    <MessageControlID>MSG001</MessageControlID>
  </Header>
  <Patient>
    <PatientID>12345</PatientID>
    <FirstName>Ion</FirstName>
    <LastName>Popescu</LastName>
    <MiddleName>Vasile</MiddleName>
    <DateOfBirth>19800115</DateOfBirth>
    <Sex>M</Sex>
    <Address>Str. Principala nr. 10, Hunedoara</Address>
    <Phone>0254123456</Phone>
    <SSN>1800115203456</SSN>
  </Patient>
  <Visit>
    <PatientClass>I</PatientClass>
    <AssignedLocation>Sectia Chirurgie</AssignedLocation>
    <AttendingDoctor>Dr. Ionescu</AttendingDoctor>
    <ReferringDoctor>Dr. Georgescu</ReferringDoctor>
    <VisitNumber>V001</VisitNumber>
    <AdmitDateTime>20240101120000</AdmitDateTime>
  </Visit>
  <Diagnosis>
    <Code>J18.9</Code>
    <Description>Pneumonie</Description>
  </Diagnosis>
  <NextOfKin>
    <Name>Maria Popescu</Name>
    <Relationship>Sotie</Relationship>
    <Phone>0254654321</Phone>
  </NextOfKin>
  <Insurance>
    <PlanID>CAS-HD</PlanID>
    <Company>CAS Hunedoara</Company>
    <Group>Grup A</Group>
  </Insurance>
</PatientMessage>
```

Parserul XML suporta:
- Mapare case-insensitive a campurilor
- Structura XML flexibila cu algoritm de aplatizare
- Combina automat `FirstName` + `LastName` in `patient_name`

---

## 7. Tabloul de Bord (Dashboard)

Pagina principala (`dashboard.php`) afiseaza:

### Panoul Stanga - Pacienti in Asteptare

- Tabel cu pacientii in coada de asteptare (status `pending`)
- Butoane de alocare pat pentru fiecare pat disponibil
- Buton de anulare pentru fiecare pacient
- **Actualizare automata** la fiecare 30 de secunde via AJAX

### Panoul Dreapta Superior - Statistici Cereri

| Statistica | Descriere |
|------------|-----------|
| Total cereri | Numarul total de cereri primite |
| Cereri azi | Cereri primite in ziua curenta |
| Cereri luna aceasta | Cereri primite in luna curenta |

Plus tabelul cu ultimele 10 cereri.

### Panoul Dreapta Mijloc - Statistici Mesaje

| Statistica | Descriere |
|------------|-----------|
| Total mesaje | Numarul total de mesaje trimise |
| Mesaje azi | Mesaje trimise in ziua curenta |
| Mesaje luna aceasta | Mesaje trimise in luna curenta |

Plus tabelul cu ultimele 10 mesaje trimise.

### Panoul Inferior Stanga - Jurnal Erori

Afiseaza ultimele 10 erori inregistrate cu mesajul si contextul fiecareia.

### Panoul Inferior Dreapta - Gestionare Utilizatori

- Lista utilizatorilor cu data crearii si rolul
- Formular de adaugare utilizator (doar manageri)
- Butoane de stergere (doar manageri, nu si pentru conturi de manager)

---

## 8. Alocarea Paturilor

### Procedura

1. Managerul vizualizeaza pacientii in asteptare pe tabloul de bord
2. Apasa butonul corespunzator patului dorit langa pacientul selectat
3. Confirma alocarea in dialogul de confirmare
4. Sistemul:
   - Construieste un mesaj **ADT^A01** din datele pacientului
   - Trimite mesajul catre sistemul de destinatie (`192.168.20.80:6600`) prin MLLP
   - Inregistreaza mesajul trimis in tabela `sent_messages`
   - Marcheaza patul ca ocupat
   - Actualizeaza statusul pacientului la `allocated`
5. Randul pacientului dispare din tabel cu animatie fade-out
6. Butonul patului alocat dispare de la toti ceilalti pacienti

### Endpoint API

```
POST /api/allocate.php
Content-Type: application/json

{"patient_id": 1, "bed_id": 2}
```

**Raspuns:**
```json
{
  "success": true,
  "bed_name": "Bed 2",
  "response_status": "success",
  "destination_response": "MSH|^~\\&|...\rMSA|AA|..."
}
```

---

## 9. Anularea Pacientilor

### Procedura

1. Managerul apasa butonul de anulare langa pacientul dorit
2. Se deschide un dialog modal pentru introducerea motivului anularii
3. Introduce motivul (camp obligatoriu) si confirma
4. Sistemul:
   - Construieste un mesaj **ADT^A11** cu segment custom **ZCR** (Cancel Reason)
   - Trimite mesajul catre sistemul de destinatie prin MLLP
   - Inregistreaza mesajul trimis
   - Actualizeaza statusul pacientului la `cancelled`
5. Randul pacientului dispare din tabel cu animatie

### Endpoint API

```
POST /api/cancel.php
Content-Type: application/json

{"patient_id": 1, "reason": "Pacientul a refuzat internarea"}
```

**Raspuns:**
```json
{
  "success": true,
  "response_status": "success"
}
```

---

## 10. Vizualizarea Cererilor

Pagina **Cereri** (`requests.php`) afiseaza toate cererile primite intr-un tabel interactiv cu:

| Coloana | Descriere |
|---------|-----------|
| ID | Identificatorul unic al cererii |
| Pacient | Numele pacientului extras |
| Format | `hl7` sau `xml` |
| IP Expeditor | Adresa IP a sistemului care a trimis datele |
| Status | Statusul pacientului (pending/allocated/cancelled) |
| Data Primirii | Data si ora primirii cererii |
| Actiuni | Buton de vizualizare detalii |

### Functionalitati

- **Cautare** in toate coloanele
- **Sortare** dupa orice coloana
- **Paginare** (25 randuri pe pagina)
- **Modal detalii** cu datele brute si campurile parsate

### Endpoint API

```
GET /api/view_request.php?id=123
```

**Raspuns:**
```json
{
  "success": true,
  "raw_data": "MSH|^~\\&|...",
  "data_format": "hl7",
  "sender_ip": "192.168.1.100",
  "received_at": "2024-01-01 12:00:00",
  "fields": {
    "patient_id": "12345",
    "patient_name": "Ion Popescu"
  }
}
```

---

## 11. Vizualizarea Mesajelor Trimise

Pagina **Mesaje** (`messages.php`) afiseaza toate mesajele trimise:

| Coloana | Descriere |
|---------|-----------|
| ID | Identificatorul mesajului |
| Pacient | Numele pacientului |
| Tip Eveniment | `bed_allocation` sau `cancellation` |
| Pat/Motiv | Patul alocat sau motivul anularii |
| Raspuns Destinatie | Raspunsul primit de la sistem |
| Status | `success`, `failure` sau `pending` |
| Data Trimiterii | Data si ora trimiterii |
| Actiuni | Buton de vizualizare mesaj HL7 complet |

### Endpoint API

```
GET /api/view_message.php?id=456
```

**Raspuns:**
```json
{
  "success": true,
  "hl7_message": "MSH|^~\\&|...",
  "event_type": "bed_allocation",
  "allocated_bed": "Bed 2",
  "cancel_reason": null,
  "destination_response": "MSH|^~\\&|...\rMSA|AA|...",
  "response_status": "success",
  "sent_at": "2024-01-01 12:05:00",
  "patient_name": "Ion Popescu"
}
```

---

## 12. Listener TCP (Serviciu de Ascultare)

Fisierul `listener.php` ruleaza ca serviciu de fundal si asculta conexiuni TCP pe portul configurat (implicit 5500).

### Pornire

```bash
php listener.php &
```

### Caracteristici

- Socket non-blocant cu `stream_select()`
- Oprire gratiosa la semnalele `SIGTERM` / `SIGINT`
- Timeout conexiune: 30 secunde
- Limita dimensiune mesaj: 1 MB
- Detecteaza automat sfarsitul cadrului MLLP (`0x1C` + `0x0D`) sau XML complet
- Proceseaza datele prin `processIncomingData()`
- Returneaza raspuns ACK (AA = succes, AE = eroare) in cadru MLLP

---

## 13. Referinta API

### Rezumat Endpoints

| Endpoint | Metoda | Autentificare | Descriere |
|----------|--------|---------------|-----------|
| `/api/receive.php` | POST | NU | Primire date pacient (HL7/XML) |
| `/api/pending.php` | GET | DA | Lista pacienti in asteptare si paturi disponibile |
| `/api/allocate.php` | POST | DA | Alocare pat unui pacient |
| `/api/cancel.php` | POST | DA | Anulare cerere pacient |
| `/api/view_request.php` | GET | DA | Detalii cerere primita |
| `/api/view_message.php` | GET | DA | Detalii mesaj trimis |
| `/api/users.php` | POST | DA | Gestionare utilizatori (adaugare/stergere) |

### GET `/api/pending.php`

Returneaza lista pacientilor in asteptare si paturile libere.

```json
{
  "pending": [
    {
      "id": 1,
      "request_id": 5,
      "patient_name": "Ion Popescu",
      "created_at": "2024-01-01 12:00:00"
    }
  ],
  "beds": [
    {"id": 1, "bed_name": "Bed 1"},
    {"id": 3, "bed_name": "Bed 3"}
  ]
}
```

### POST `/api/users.php`

**Adaugare utilizator:**
```json
{"action": "add", "username": "utilizator_nou", "password": "parola123"}
```

**Stergere utilizator:**
```json
{"action": "delete", "user_id": 5}
```

**Validari:**
- Numele de utilizator trebuie sa fie unic
- Parola trebuie sa aiba minim 4 caractere
- Conturile de manager nu pot fi sterse
- Doar managerii pot sterge utilizatori

---

## 14. Structura Bazei de Date

### Tabela `raw_requests`

Stocheaza toate datele brute primite.

| Camp | Tip | Descriere |
|------|-----|-----------|
| `id` | INT, PK | Identificator unic |
| `raw_data` | LONGTEXT | Datele brute HL7/XML |
| `data_format` | ENUM('hl7','xml') | Formatul detectat |
| `sender_ip` | VARCHAR(45) | IP-ul expeditorului |
| `received_at` | DATETIME | Data si ora primirii |

### Tabela `patient_data`

Model Entity-Attribute-Value (EAV) pentru datele parsate ale pacientilor.

| Camp | Tip | Descriere |
|------|-----|-----------|
| `id` | INT, FK | Referinta la `raw_requests.id` |
| `field_name` | VARCHAR(255) | Numele campului (ex: `patient_name`) |
| `field_value` | TEXT | Valoarea campului |

### Tabela `pending_patients`

Coada pacientilor in asteptare.

| Camp | Tip | Descriere |
|------|-----|-----------|
| `id` | INT, PK | Identificator unic |
| `request_id` | INT, FK | Referinta la cerere |
| `patient_name` | VARCHAR(255) | Numele pacientului |
| `status` | ENUM('pending','allocated','cancelled') | Statusul curent |
| `created_at` | DATETIME | Data crearii |

### Tabela `beds`

Gestionarea paturilor spitalicesti.

| Camp | Tip | Descriere |
|------|-----|-----------|
| `id` | INT, PK | Identificator unic |
| `bed_name` | VARCHAR(50) | Numele patului (ex: "Bed 1") |
| `is_occupied` | TINYINT | 0 = liber, 1 = ocupat |
| `occupied_by` | INT, FK | Referinta la pacientul alocat |

### Tabela `sent_messages`

Jurnalul mesajelor HL7 trimise.

| Camp | Tip | Descriere |
|------|-----|-----------|
| `id` | INT, PK | Identificator unic |
| `pending_patient_id` | INT, FK | Referinta la pacient |
| `hl7_message` | LONGTEXT | Mesajul HL7 complet |
| `event_type` | ENUM('bed_allocation','cancellation') | Tipul evenimentului |
| `allocated_bed` | VARCHAR(50) | Patul alocat (daca este cazul) |
| `cancel_reason` | TEXT | Motivul anularii (daca este cazul) |
| `destination_response` | TEXT | Raspunsul sistemului de destinatie |
| `response_status` | ENUM('success','failure','pending') | Statusul raspunsului |
| `sent_at` | DATETIME | Data si ora trimiterii |

### Tabela `error_log`

Jurnalul erorilor aplicatiei.

| Camp | Tip | Descriere |
|------|-----|-----------|
| `id` | INT, PK | Identificator unic |
| `error_message` | TEXT | Descrierea erorii |
| `error_context` | VARCHAR(255) | Contextul in care a aparut eroarea |
| `created_at` | DATETIME | Data si ora inregistrarii |

### Tabela `users`

Conturile de utilizator.

| Camp | Tip | Descriere |
|------|-----|-----------|
| `id` | INT, PK | Identificator unic |
| `username` | VARCHAR(100), UNIQUE | Numele de utilizator |
| `password_hash` | VARCHAR(255) | Parola criptata Bcrypt |
| `is_manager` | TINYINT | 0 = utilizator, 1 = manager |
| `created_at` | DATETIME | Data crearii contului |

---

## 15. Jurnalizarea Erorilor

Toate erorile sunt inregistrate automat in tabela `error_log`:

- Erori de conexiune la baza de date
- Erori de conexiune socket
- Erori de parsare MLLP
- Erori de parsare XML
- Erori de tranzactie (cu rollback automat)
- Erori AJAX afisate in interfata web

Ultimele 10 erori sunt vizibile pe tabloul de bord.

---

## 16. Securitate

### Masuri Implementate

| Masura | Implementare |
|--------|-------------|
| **Autentificare** | Sesiuni PHP cu timeout de 1 ora |
| **Criptare parole** | Algoritm Bcrypt (`PASSWORD_BCRYPT`) |
| **Protectie SQL Injection** | Interogari parametrizate (Prepared Statements) in toate operatiile |
| **Protectie XSS** | `htmlspecialchars()` in PHP, `escapeHtml()` in JavaScript |
| **Control acces** | Verificare rol manager pentru operatii sensibile |
| **Protectie conturi manager** | Conturile de manager nu pot fi sterse |
| **Endpoint public** | Doar `/api/receive.php` este accesibil fara autentificare (intentionat, pentru sisteme externe) |

### Recomandari Suplimentare

- Adaugati protectie CSRF cu token-uri in formulare
- Configurati HTTPS pe serverul web
- Restrictionati accesul la `/api/receive.php` prin firewall (doar IP-uri de incredere)
- Schimbati parola implicita a managerului dupa prima autentificare

---

*Document generat pentru Patient Hub - Sistem de Gestionare a Admiterilor Spitalicesti*
