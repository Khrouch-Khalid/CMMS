DROP TABLE IF EXISTS Employee_Status;
-- Drop and recreate the entire database
DROP DATABASE IF EXISTS cmms;
CREATE DATABASE cmms;
USE cmms;

create table Employee_Status
(
  Status_ID SMALLINT primary key AUTO_INCREMENT,
  Status_Name varchar(50) not null,
  Description varchar(300)
);
create table Shift_Type
(
  Shift_Type_ID SMALLINT primary key AUTO_INCREMENT,
  Shift_Name varchar(60) not null unique,
  Start_Time time not null,
  End_Time time not null,
  Description varchar(300)
);
create table Employees
(
  Employee_ID int primary key AUTO_INCREMENT,
  National_ID varchar(20)not null unique,
  First_Name varchar(60)not null,
  Last_Name varchar(60)not null,
  Phone varchar(25),
  Email varchar(100) not null unique,
  Date_Of_Birth date not null,
  Hire_Date date not null,
  Resign_Date date,
  Contract_Start_Date date not null,
  Contract_End_Date date not null,
  Status_ID SMALLINT not null,
  Shift_Type_ID SMALLINT not null,
  
  foreign key (Status_ID) references Employee_Status(Status_ID),
  foreign key (Shift_Type_ID) references Shift_Type(Shift_Type_ID)
);
create table Biomedical_Engineers
(
  Engineer_ID int primary key AUTO_INCREMENT,
  Employee_ID INT not null unique,
  
  foreign key (Employee_ID) references Employees(Employee_ID)
);
CREATE TABLE Specializations
(
  Specialization_ID SMALLINT PRIMARY KEY AUTO_INCREMENT,
  Specialization_Name VARCHAR(60) NOT NULL UNIQUE,
  Description VARCHAR(300)
);
CREATE TABLE Departments
(
  Department_ID SMALLINT PRIMARY KEY AUTO_INCREMENT,
  Department_Name VARCHAR(60) NOT NULL UNIQUE,
  Contact_Email VARCHAR(100) NOT NULL,
  Contact_Phone VARCHAR(30) NOT NULL,
  Description VARCHAR(300)
);
CREATE TABLE Technicians
(
  Technician_ID SMALLINT PRIMARY KEY AUTO_INCREMENT,
  Employee_ID INT NOT NULL UNIQUE,
  Department_ID SMALLINT NOT NULL,
  Specialization_ID SMALLINT NOT NULL,
  Managed_ByEngineer_ID INT NOT NULL,

  FOREIGN KEY (Employee_ID) REFERENCES Employees(Employee_ID),
  FOREIGN KEY (Department_ID) REFERENCES Departments(Department_ID),
  FOREIGN KEY (Specialization_ID) REFERENCES Specializations(Specialization_ID),
  FOREIGN KEY (Managed_ByEngineer_ID) REFERENCES Biomedical_Engineers(Engineer_ID)
);
CREATE TABLE Suppliers
(
  Supplier_ID INT PRIMARY KEY AUTO_INCREMENT,
  Supplier_Name VARCHAR(150) NOT NULL,
  Phone_Number VARCHAR(30),
  Email VARCHAR(100),
  Address VARCHAR(250),
  City VARCHAR(100),
  Country VARCHAR(100)
);
CREATE TABLE Asset_Types
(
  Type_ID SMALLINT PRIMARY KEY AUTO_INCREMENT,
  Type_Name VARCHAR(60) NOT NULL,
  Description VARCHAR(300)
);
CREATE TABLE Asset_Status
(
  Status_ID SMALLINT PRIMARY KEY AUTO_INCREMENT,
  Status_Name VARCHAR(60) NOT NULL UNIQUE,
  Description VARCHAR(300)
);
CREATE TABLE Criticality_Level
(
  Level_ID SMALLINT PRIMARY KEY AUTO_INCREMENT,
  Level_Name VARCHAR(60) NOT NULL UNIQUE,
  Description VARCHAR(300),
  Response_Time_Hours SMALLINT NOT NULL
);
CREATE TABLE Assets
(
  Asset_ID SMALLINT PRIMARY KEY AUTO_INCREMENT,
  Asset_Name VARCHAR(60) NOT NULL UNIQUE,
  Serial_Number VARCHAR(60),
  Asset_Type_ID SMALLINT NOT NULL,
  Model VARCHAR(20),
  Department_ID SMALLINT NOT NULL,
  Installation_Date DATE,
  Warranty_Expiry DATE,
  Status_ID SMALLINT NOT NULL,
  Purchase_Cost DECIMAL(12,2),
  Last_Maintenance_Date DATE,
  Next_Maintenance_Date DATE,
  Criticality_Level_ID SMALLINT NOT NULL,
  Supplier_ID INT NOT NULL,
  Additional_Notes VARCHAR(500),

  FOREIGN KEY (Asset_Type_ID) REFERENCES Asset_Types(Type_ID),
  FOREIGN KEY (Department_ID) REFERENCES Departments(Department_ID),
  FOREIGN KEY (Status_ID) REFERENCES Asset_Status(Status_ID),
  FOREIGN KEY (Criticality_Level_ID) REFERENCES Criticality_Level(Level_ID),
  FOREIGN KEY (Supplier_ID) REFERENCES Suppliers(Supplier_ID)
);
CREATE TABLE Maintenance_Priority
(
  Priority_ID SMALLINT PRIMARY KEY AUTO_INCREMENT,
  Priority_Name VARCHAR(60) NOT NULL UNIQUE,
  Priority_Level TINYINT NOT NULL,
  Response_Time_Hours SMALLINT NOT NULL,
  Description VARCHAR(300)
);
CREATE TABLE Maintenance_Type
(
  Type_ID SMALLINT PRIMARY KEY AUTO_INCREMENT,
  Type_Name VARCHAR(60) NOT NULL UNIQUE,
  Description VARCHAR(300),
  Is_Planned BOOLEAN NOT NULL DEFAULT TRUE
);
CREATE TABLE Maintenance
(
  Maintenance_ID INT PRIMARY KEY AUTO_INCREMENT,
  Asset_ID SMALLINT NOT NULL,
  Type_ID SMALLINT NOT NULL,
  Priority_ID SMALLINT NOT NULL,
  Reported_Date DATETIME NOT NULL,
  Scheduled_Date DATETIME NOT NULL,
  Started_Date DATETIME,
  Completed_Date DATETIME,
  Assigned_Engineer_ID INT NOT NULL,
  Assigned_Technician_ID SMALLINT,
  Description VARCHAR(300),

  FOREIGN KEY (Asset_ID) REFERENCES Assets(Asset_ID),
  FOREIGN KEY (Type_ID) REFERENCES Maintenance_Type(Type_ID),
  FOREIGN KEY (Priority_ID) REFERENCES Maintenance_Priority(Priority_ID),
  FOREIGN KEY (Assigned_Engineer_ID) REFERENCES Biomedical_Engineers(Engineer_ID),
  FOREIGN KEY (Assigned_Technician_ID) REFERENCES Technicians(Technician_ID)
);
CREATE TABLE Technician_Task_Role
(
  Role_ID SMALLINT PRIMARY KEY AUTO_INCREMENT,
  Role_Name VARCHAR(50) NOT NULL UNIQUE,
  Description VARCHAR(300)
);
CREATE TABLE Parts_Category
(
  Category_ID SMALLINT PRIMARY KEY AUTO_INCREMENT,
  Category_Name VARCHAR(50) NOT NULL UNIQUE,
  Description VARCHAR(300)
);
CREATE TABLE Spare_Parts
(
  Part_ID SMALLINT PRIMARY KEY AUTO_INCREMENT,
  Part_Name VARCHAR(50) NOT NULL UNIQUE,
  Supplier_ID INT NOT NULL,
  Category_ID SMALLINT NOT NULL,
  Unit_Price DECIMAL(10,2) NOT NULL,
  Current_Stock INT NOT NULL,
  Is_Critical BOOLEAN NOT NULL,

  FOREIGN KEY (Supplier_ID) REFERENCES Suppliers(Supplier_ID),
  FOREIGN KEY (Category_ID) REFERENCES Parts_Category(Category_ID)
);
CREATE TABLE Maintenance_Task
(
  Task_ID INT PRIMARY KEY AUTO_INCREMENT,
  Maintenance_ID INT NOT NULL,
  Task_Description VARCHAR(300),
  Planned_Hours DECIMAL(5,2) NOT NULL,
  ActualHours DECIMAL(5,2),

  FOREIGN KEY (Maintenance_ID) REFERENCES Maintenance(Maintenance_ID)
);
CREATE TABLE Task_Parts_Usage
(
  Usage_ID INT PRIMARY KEY AUTO_INCREMENT,
  Task_ID INT NOT NULL,
  Spare_Part_ID SMALLINT NOT NULL,
  Quantity SMALLINT NOT NULL,
  Used_ByEngineer_ID INT NOT NULL,
  Used_Date DATE NOT NULL,
  Notes VARCHAR(300),

  FOREIGN KEY (Task_ID) REFERENCES Maintenance_Task(Task_ID),
  FOREIGN KEY (Spare_Part_ID) REFERENCES Spare_Parts(Part_ID),
  FOREIGN KEY (Used_ByEngineer_ID) REFERENCES Biomedical_Engineers(Engineer_ID)
);
CREATE TABLE Task_Technicians
(
  Task_Technician_ID INT PRIMARY KEY AUTO_INCREMENT,
  Task_ID INT NOT NULL,
  Technician_ID SMALLINT NOT NULL,
  HoursWorked DECIMAL(5, 2),
  AssignedAt DATETIME NOT NULL,
  Technician_Role_ID SMALLINT NOT NULL,

  FOREIGN KEY (Task_ID) REFERENCES Maintenance_Task(Task_ID),
  FOREIGN KEY (Technician_ID) REFERENCES Technicians(Technician_ID),
  FOREIGN KEY (Technician_Role_ID) REFERENCES Technician_Task_Role(Role_ID)
);

CREATE TABLE Panne_Declarations
(
  Panne_Declaration_ID INT PRIMARY KEY AUTO_INCREMENT,
  Technician_ID SMALLINT NOT NULL,
  Asset_ID SMALLINT NOT NULL,
  Description VARCHAR(500) NOT NULL,
  Severity VARCHAR(50) NOT NULL DEFAULT 'Normal',
  Reported_Date DATETIME NOT NULL,
  Status VARCHAR(50) NOT NULL DEFAULT 'En attente',
  Engineer_Response VARCHAR(500),
  Accepted_Date DATETIME,
  Accepted_By_Engineer_ID INT,
  
  FOREIGN KEY (Technician_ID) REFERENCES Technicians(Technician_ID),
  FOREIGN KEY (Asset_ID) REFERENCES Assets(Asset_ID),
  FOREIGN KEY (Accepted_By_Engineer_ID) REFERENCES Biomedical_Engineers(Engineer_ID)
);

-- Create Maintenance_Reports table for storing intervention reports
CREATE TABLE IF NOT EXISTS Maintenance_Reports (
    Report_ID INT PRIMARY KEY AUTO_INCREMENT,
    Maintenance_ID INT NOT NULL,
    Work_Description TEXT NOT NULL,
    Maintenance_Type VARCHAR(50),
    Failure_Cause TEXT,
    Parts_Used TEXT,
    Parts_Ordered TEXT,
    Work_Completed BOOLEAN DEFAULT FALSE,
    Client_Satisfaction VARCHAR(50),
    Hours_Worked FLOAT DEFAULT 0,
    Report_Date DATETIME DEFAULT CURRENT_TIMESTAMP,
    Technician_ID INT,
    FOREIGN KEY (Maintenance_ID) REFERENCES Maintenance(Maintenance_ID) ON DELETE CASCADE,
    FOREIGN KEY (Technician_ID) REFERENCES Employees(Employee_ID) ON DELETE SET NULL,
    INDEX idx_maintenance (Maintenance_ID),
    INDEX idx_report_date (Report_Date)
);

-- Add column to Maintenance table to track if report filed (optional)
ALTER TABLE Maintenance ADD COLUMN Report_Filed BOOLEAN DEFAULT FALSE;
ALTER TABLE Maintenance ADD COLUMN Report_ID INT;
ALTER TABLE Maintenance ADD FOREIGN KEY (Report_ID) REFERENCES Maintenance_Reports(Report_ID);




-- ============================================================================
-- Users/Login Credentials
-- ============================================================================
CREATE TABLE Users
(
  User_ID INT PRIMARY KEY AUTO_INCREMENT,
  Email VARCHAR(100) NOT NULL UNIQUE,
  Password VARCHAR(255) NOT NULL,
  Role VARCHAR(50) NOT NULL,
  Employee_ID INT,
  Created_At DATETIME DEFAULT CURRENT_TIMESTAMP,
  Last_Login DATETIME,
  Is_Active BOOLEAN DEFAULT TRUE,
  
  FOREIGN KEY (Employee_ID) REFERENCES Employees(Employee_ID)
);

-- ============================================================================
-- CMMS Hospital - Données de Démonstration Marocaines en Français
-- ============================================================================

-- ============================================================================
-- 1. Statut des Employés
-- ============================================================================
INSERT INTO Employee_Status (Status_Name, Description) VALUES
('Actif', 'L\'employé est actif et travaille'),
('Inactif', 'L\'employé est inactif'),
('En congé', 'L\'employé est en congé'),
('Retraité', 'L\'employé est retraité');

-- ============================================================================
-- 2. Types de Quarts
-- ============================================================================
INSERT INTO Shift_Type (Shift_Name, Start_Time, End_Time, Description) VALUES
('Matin', '08:00:00', '16:00:00', 'Quart du matin de 8h à 16h'),
('Soir', '16:00:00', '24:00:00', 'Quart du soir de 16h à minuit'),
('Nuit', '00:00:00', '08:00:00', 'Quart de nuit de minuit à 8h'),
('Quart complet', '08:00:00', '20:00:00', 'Quart de 12 heures');

-- ============================================================================
-- 3. Employés - Ingénieurs Biomédicaux
-- ============================================================================
INSERT INTO Employees (National_ID, First_Name, Last_Name, Phone, Email, Date_Of_Birth, Hire_Date, Contract_Start_Date, Contract_End_Date, Status_ID, Shift_Type_ID) VALUES
('AB123456', 'Melkaoui', 'Monsif', '+212612345678', 'melkaoui.monsif@hospital.ma', '1985-03-15', '2015-01-01', '2015-01-01', '2025-12-31', 1, 1),
('AB234567', 'Ali', 'Malki', '+212612345679', 'ali.malki@hospital.ma', '1988-07-22', '2016-02-15', '2016-02-15', '2026-02-14', 1, 1),
('AB345678', 'Fatima', 'Farsi', '+212612345680', 'fatima.farsi@hospital.ma', '1990-11-08', '2017-03-20', '2017-03-20', '2027-03-19', 1, 1),
('AB456789', 'Omar', 'Mourabit', '+212612345681', 'omar.mourabit@hospital.ma', '1987-05-30', '2014-06-01', '2014-06-01', '2024-05-31', 1, 2);

-- ============================================================================
-- 4. Ingénieurs Biomédicaux
-- ============================================================================
INSERT INTO Biomedical_Engineers (Employee_ID) VALUES (1), (2), (3), (4);

-- ============================================================================
-- 5. Spécialisations
-- ============================================================================
INSERT INTO Specializations (Specialization_Name, Description) VALUES
('Équipements Médicaux Généraux', 'Maintenance des équipements médicaux généraux'),
('Traitement d\'Images', 'Spécialiste des équipements de traitement d\'images (X-Ray, CT, IRM)'),
('Laboratoires', 'Spécialiste des équipements de laboratoire et d\'analyses'),
('Respiration et Soins Intensifs', 'Spécialiste des équipements de respiration artificielle et soins intensifs'),
('Électricité Médicale', 'Spécialiste des systèmes électriques et des alarmes');

-- ============================================================================
-- 6. Départements
-- ============================================================================
INSERT INTO Departments (Department_Name, Contact_Email, Contact_Phone, Description) VALUES
('Chirurgie', 'chirurgie@hospital.ma', '+212512345000', 'Département de Chirurgie Générale'),
('Radiologie', 'radiologie@hospital.ma', '+212512345001', 'Département de Radiologie et d\'Imagerie Médicale'),
('Laboratoires', 'labo@hospital.ma', '+212512345002', 'Département des Analyses Médicales et Laboratoires'),
('Soins Intensifs', 'soins.intensifs@hospital.ma', '+212512345003', 'Département des Soins Intensifs et Respiration Artificielle'),
('Dentisterie', 'dentaire@hospital.ma', '+212512345004', 'Département de Dentisterie'),
('Urgences', 'urgences@hospital.ma', '+212512345005', 'Département des Urgences et Premiers Secours');

-- ============================================================================
-- 7. Techniciens
-- ============================================================================
INSERT INTO Employees (National_ID, First_Name, Last_Name, Phone, Email, Date_Of_Birth, Hire_Date, Contract_Start_Date, Contract_End_Date, Status_ID, Shift_Type_ID) VALUES
('AB567890', 'Hassan', 'Hassani', '+212612345682', 'hassan.hassani@hospital.ma', '1992-02-14', '2018-04-10', '2018-04-10', '2028-04-09', 1, 1),
('AB678901', 'Mariam', 'Andalusi', '+212612345683', 'mariam.andalusi@hospital.ma', '1991-09-25', '2019-05-15', '2019-05-15', '2029-05-14', 1, 2),
('AB789012', 'Mahmoud', 'Chaui', '+212612345684', 'mahmoud.chaui@hospital.ma', '1993-12-03', '2017-06-20', '2017-06-20', '2027-06-19', 1, 1),
('AB890123', 'Rabah', 'Wazani', '+212612345685', 'rabah.wazani@hospital.ma', '1994-08-17', '2020-07-01', '2020-07-01', '2030-06-30', 1, 2);

-- ============================================================================
-- 8. Techniciens Liés aux Employés
-- ============================================================================
INSERT INTO Technicians (Employee_ID, Department_ID, Specialization_ID, Managed_ByEngineer_ID) VALUES
(5, 1, 1, 1),
(6, 2, 2, 2),
(7, 3, 3, 1),
(8, 4, 4, 3);

-- ============================================================================
-- 9. Fournisseurs
-- ============================================================================
INSERT INTO Suppliers (Supplier_Name, Phone_Number, Email, Address, City, Country) VALUES
('Fares Medical Company', '+212212345678', 'contact@alfares.ma', 'Avenue Mohamed V', 'Rabat', 'Maroc'),
('Madar Medical Equipment', '+212212345679', 'info@madar-medical.ma', 'Quartier Razi', 'Casablanca', 'Maroc'),
('Advanced Medical Equipment', '+212212345680', 'sales@atpm.ma', 'Avenue de la Solidarité', 'Fès', 'Maroc'),
('Nejm Medical Solutions', '+212212345681', 'contact@nejm-medical.ma', 'Avenue Principale', 'Marrakech', 'Maroc'),
('Quality Medical Supplies', '+212212345682', 'info@quality-medical.ma', 'Quartier Al Hoceima', 'Tanger', 'Maroc');

-- ============================================================================
-- 10. Types d\'Actifs
-- ============================================================================
INSERT INTO Asset_Types (Type_Name, Description) VALUES
('Équipements d\'Imagerie', 'Équipements de radiologie et d\'imagerie médicale'),
('Équipements de Laboratoire', 'Équipements d\'analyse et de laboratoire'),
('Équipements Respiratoires', 'Équipements de respiration artificielle et d\'oxygène'),
('Moniteurs Vitaux', 'Équipements de surveillance des signes vitaux'),
('Équipements Chirurgicaux', 'Équipements et instruments chirurgicaux'),
('Pompes d\'Infusion', 'Pompes d\'infusion et perfusion'),
('Autres Équipements', 'Divers équipements médicaux');

-- ============================================================================
-- 11. Statut des Actifs
-- ============================================================================
INSERT INTO Asset_Status (Status_Name, Description) VALUES
('Opérationnel', 'L\'équipement est opérationnel et prêt à l\'emploi'),
('En Maintenance', 'L\'équipement est actuellement en maintenance'),
('Hors Service', 'L\'équipement est hors service et inutilisable'),
('Secours', 'L\'équipement est réservé comme secours'),
('Retiré du Service', 'L\'équipement est ancien et retiré du service');

-- ============================================================================
-- 12. Niveaux de Criticité
-- ============================================================================
INSERT INTO Criticality_Level (Level_Name, Description, Response_Time_Hours) VALUES
('Critique', 'Équipement critique - intervention immédiate obligatoire', 1),
('Très Important', 'Équipement très important - réponse rapide requise', 4),
('Important', 'Équipement important - réponse standard', 24),
('Normal', 'Équipement normal - maintenance routinière', 0);

-- ============================================================================
-- 13. Actifs
-- ============================================================================
INSERT INTO Assets (Asset_Name, Asset_Type_ID, Model, Department_ID, Installation_Date, Warranty_Expiry, Status_ID, Purchase_Cost, Last_Maintenance_Date, Next_Maintenance_Date, Criticality_Level_ID, Supplier_ID) VALUES
('Appareil Radiographie X-Ray 1', 1, 'Siemens XP 200', 2, '2020-01-15', '2025-01-14', 1, 150000.00, '2024-12-01', '2025-03-01', 1, 1),
('Scanner CT', 1, 'GE Lightspeed 64', 2, '2019-06-20', '2024-06-19', 1, 500000.00, '2024-11-15', '2025-02-15', 1, 2),
('Appareil IRM', 1, 'Philips Ingenia', 2, '2018-03-10', '2023-03-09', 1, 1200000.00, '2024-10-30', '2025-01-30', 1, 3),
('Analyseur Hématologie', 2, 'Sysmex XN-1000', 3, '2021-02-14', '2026-02-13', 1, 80000.00, '2024-11-20', '2025-02-20', 2, 4),
('Analyseur de Biochimie', 2, 'Roche Cobas 8000', 3, '2020-09-01', '2025-08-31', 1, 120000.00, '2024-12-05', '2025-03-05', 2, 1),
('Respirateur Artificiel ICU 1', 3, 'Philips Respironics E30', 4, '2022-04-15', '2027-04-14', 1, 50000.00, '2024-12-10', '2025-03-10', 1, 2),
('Respirateur Artificiel ICU 2', 3, 'Hamilton C3', 4, '2021-08-20', '2026-08-19', 1, 55000.00, '2024-11-25', '2025-02-25', 1, 1),
('Moniteur Signes Vitaux 1', 4, 'Philips IntelliVue MP70', 4, '2020-11-10', '2025-11-09', 1, 35000.00, '2024-12-15', '2025-03-15', 2, 3),
('Pompe Perfusion IV 1', 6, 'Baxter Infusion Pump', 4, '2019-07-05', '2024-07-04', 3, 25000.00, '2024-10-01', '2025-01-01', 2, 4),
('Pompe Perfusion IV 2', 6, 'B. Braun Infusomat', 1, '2021-05-18', '2026-05-17', 1, 28000.00, '2024-11-30', '2025-02-28', 2, 2),
('Fauteuil Dentaire 1', 5, 'Sirona CEREC Prime', 5, '2018-02-28', '2023-02-27', 1, 45000.00, '2024-12-01', '2025-03-01', 3, 1),
('Analyseur Urine', 2, 'Sysmex UX-5000', 3, '2022-01-10', '2027-01-09', 1, 65000.00, '2024-11-20', '2025-02-20', 3, 5);

-- ============================================================================
-- 14. Priorités de Maintenance
-- ============================================================================
INSERT INTO Maintenance_Priority (Priority_Name, Priority_Level, Response_Time_Hours, Description) VALUES
('Urgence Critique', 1, 1, 'Intervention immédiate requise - équipement en panne'),
('Urgence', 2, 4, 'Très urgent - affecte les opérations'),
('Haute', 3, 8, 'Haute priorité - affecte les patients'),
('Normale', 4, 24, 'Priorité normale - peut être reportée légèrement'),
('Basse', 5, 72, 'Basse priorité - maintenance routinière');

-- ============================================================================
-- 15. Types de Maintenance
-- ============================================================================
INSERT INTO Maintenance_Type (Type_Name, Description, Is_Planned) VALUES
('Maintenance Préventive', 'Maintenance routinière pour prévenir les pannes', TRUE),
('Maintenance Corrective', 'Réparation des pannes urgentes', FALSE),
('Étalonnage et Vérification', 'Étalonnage et vérification de la précision des équipements', TRUE),
('Mise à Jour Logicielle', 'Mise à jour des logiciels et applications des équipements', TRUE),
('Inspection Périodique', 'Inspection complète pour la sécurité et la qualité', TRUE);

-- ============================================================================
-- 16. Enregistrements de Maintenance
-- ============================================================================
INSERT INTO Maintenance (Asset_ID, Type_ID, Priority_ID, Reported_Date, Scheduled_Date, Started_Date, Completed_Date, Assigned_Engineer_ID, Description) VALUES
(1, 1, 4, '2024-12-01 08:00:00', '2024-12-02 08:00:00', '2024-12-02 09:00:00', '2024-12-02 14:00:00', 1, 'Maintenance préventive de l\'appareil radiographie'),
(2, 3, 4, '2024-11-20 10:00:00', '2024-11-22 10:00:00', '2024-11-22 08:00:00', '2024-11-22 16:00:00', 2, 'Étalonnage et vérification de précision du scanner'),
(3, 1, 3, '2024-11-15 14:00:00', '2024-11-17 14:00:00', '2024-11-17 10:00:00', '2024-11-19 12:00:00', 3, 'Maintenance complète de l\'appareil IRM'),
(4, 1, 4, '2024-11-25 09:00:00', '2024-11-26 09:00:00', '2024-11-26 08:30:00', '2024-11-26 13:00:00', 1, 'Maintenance préventive de l\'analyseur hématologie'),
(9, 2, 1, '2024-12-10 15:30:00', '2024-12-10 16:00:00', '2024-12-10 16:15:00', '2024-12-10 19:00:00', 2, 'Réparation urgente de la pompe perfusion IV'),
(6, 1, 3, '2024-12-05 11:00:00', '2024-12-06 11:00:00', NULL, NULL, 4, 'Maintenance préventive planifiée du respirateur artificiel'),
(7, 1, 4, '2024-12-08 07:00:00', '2024-12-09 07:00:00', '2024-12-09 08:00:00', '2024-12-09 16:30:00', 3, 'Maintenance préventive du respirateur ICU 2');

-- ============================================================================
-- 17. Rôles des Tâches Technicien
-- ============================================================================
INSERT INTO Technician_Task_Role (Role_Name, Description) VALUES
('Assistant Maintenance', 'Assistance pour les tâches de maintenance de base'),
('Technicien Certifié', 'Technicien certifié pour les réparations'),
('Technicien Principal', 'Technicien responsable de la tâche'),
('Superviseur', 'Superviseur de l\'opération de maintenance');

-- ============================================================================
-- 18. Catégories de Pièces
-- ============================================================================
INSERT INTO Parts_Category (Category_Name, Description) VALUES
('Pièces Électriques', 'Composants et pièces électriques'),
('Pièces Mécaniques', 'Pièces mécaniques et mobiles'),
('Composants Électroniques', 'Circuits et composants électroniques'),
('Filtres et Membranes', 'Filtres à air et membranes'),
('Fluides et Huiles', 'Fluides hydrauliques et huiles'),
('Pièces Plastiques', 'Pièces en plastique et isolants'),
('Autres Accessoires', 'Accessoires variés');

-- ============================================================================
-- 19. Pièces de Rechange
-- ============================================================================
INSERT INTO Spare_Parts (Part_Name, Supplier_ID, Category_ID, Unit_Price, Current_Stock, Is_Critical) VALUES
('Ampoule Radiographie', 1, 1, 2500.00, 3, 1),
('Filtre Air Principal', 1, 4, 150.00, 15, 0),
('Batterie Respirateur', 2, 1, 800.00, 8, 1),
('Carte de Contrôle Principal', 3, 3, 5000.00, 2, 1),
('Tube Connexion Médical', 4, 6, 50.00, 50, 0),
('Filtre Sanguin', 2, 4, 120.00, 20, 0),
('Panneau Protection Électrique', 1, 1, 1200.00, 4, 1),
('Pompe Vide', 5, 2, 3500.00, 1, 1),
('Capteur Pression', 3, 3, 450.00, 6, 1),
('Courroie Transmission', 4, 2, 200.00, 10, 0);

-- ============================================================================
-- 20. Tâches de Maintenance
-- ============================================================================
INSERT INTO Maintenance_Task (Maintenance_ID, Task_Description, Planned_Hours, ActualHours) VALUES
(1, 'Inspection et nettoyage des tubes radiographiques', 2.0, 2.5),
(1, 'Test de performance et étalonnage', 2.0, 2.0),
(2, 'Inspection du système de refroidissement', 4.0, 4.5),
(2, 'Étalonnage de la caméra et des capteurs', 3.0, 3.2),
(3, 'Inspection complète des systèmes électriques', 6.0, 6.5),
(3, 'Test de la force du magnétique', 4.0, 4.3),
(4, 'Nettoyage des détecteurs', 1.5, 1.7),
(4, 'Étalonnage de l\'équipement', 2.0, 2.0),
(5, 'Diagnostic de la panne', 1.0, 0.8),
(5, 'Remplacement de la batterie', 1.0, 0.9),
(5, 'Test du système', 1.0, 1.1),
(7, 'Inspection des tuyaux et connexions', 2.0, 2.2),
(7, 'Étalonnage des capteurs', 3.0, 3.1);

-- ============================================================================
-- 21. Utilisation de Pièces par Tâche
-- ============================================================================
INSERT INTO Task_Parts_Usage (Task_ID, Spare_Part_ID, Quantity, Used_ByEngineer_ID, Used_Date, Notes) VALUES
(1, 2, 1, 1, '2024-12-02', 'Remplacement du filtre air principal'),
(3, 2, 2, 2, '2024-11-22', 'Nettoyage et changement des filtres'),
(5, 7, 1, 3, '2024-11-17', 'Remplacement du panneau protection électrique'),
(8, 9, 1, 1, '2024-11-26', 'Remplacement du capteur de pression'),
(10, 3, 2, 2, '2024-12-10', 'Remplacement des batteries du respirateur'),
(11, 5, 3, 2, '2024-12-10', 'Inspection et vérification des tuyaux');

-- ============================================================================
-- 22. Techniciens Affectés aux Tâches
-- ============================================================================
INSERT INTO Task_Technicians (Task_ID, Technician_ID, HoursWorked, AssignedAt, Technician_Role_ID) VALUES
(1, 1, 2.5, '2024-12-02 09:00:00', 2),
(2, 1, 2.0, '2024-12-02 11:30:00', 3),
(3, 2, 4.5, '2024-11-22 08:00:00', 2),
(4, 2, 3.2, '2024-11-22 12:30:00', 3),
(7, 4, 2.2, '2024-12-09 08:00:00', 2),
(8, 4, 3.1, '2024-12-09 10:15:00', 3),
(10, 3, 0.9, '2024-12-10 16:30:00', 1),
(11, 3, 1.1, '2024-12-10 17:20:00', 2);

-- ============================================================================
-- Fin des Données de Démonstration
-- ============================================================================

-- ============================================================================
-- 23. Maintenance_Reports Table (Intervention Reports)
-- ============================================================================
CREATE TABLE IF NOT EXISTS Maintenance_Reports (
    Report_ID INT PRIMARY KEY AUTO_INCREMENT,
    Maintenance_ID INT NOT NULL,
    Work_Description TEXT NOT NULL,
    Maintenance_Type VARCHAR(50),
    Failure_Cause TEXT,
    Parts_Used TEXT,
    Parts_Ordered TEXT,
    Work_Completed BOOLEAN DEFAULT FALSE,
    Client_Satisfaction VARCHAR(50),
    Hours_Worked FLOAT DEFAULT 0,
    Report_Date DATETIME DEFAULT CURRENT_TIMESTAMP,
    Technician_ID INT,
    FOREIGN KEY (Maintenance_ID) REFERENCES Maintenance(Maintenance_ID) ON DELETE CASCADE,
    FOREIGN KEY (Technician_ID) REFERENCES Employees(Employee_ID) ON DELETE SET NULL,
    INDEX idx_maintenance (Maintenance_ID),
    INDEX idx_report_date (Report_Date),
    INDEX idx_technician (Technician_ID)
);
