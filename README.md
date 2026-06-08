# Final-Year-Project-and-Research-Management-System-FYPRMS-

The Final Year Project and Research Management System (FYPRMS) is a web-based platform designed to provide a centralized repository for storing, managing, searching, and accessing final year academic projects and research papers from universities across Tanzania. The system addresses the challenges associated with manual storage, fragmented repositories, duplication of research topics, and limited accessibility of academic resources. By digitizing project and research management processes, FYPRMS enhances knowledge sharing, promotes collaboration, preserves academic outputs, and supports research-driven innovation within higher learning institutions

The system serves multiple stakeholders, including students, supervisors, researchers, librarians, and administrators. It enables secure submission of final year projects and research papers, efficient review and approval processes, advanced search functionality, and easy retrieval of academic documents. Through a centralized platform, universities can better manage their research assets while students and researchers gain access to valuable references for future studies.

Problem Statement

Many universities in Tanzania still store final year projects and research papers manually or in isolated departmental repositories. As a result:

Research findings are difficult to access.
Valuable academic work may be lost or damaged.
Students often repeat previously completed research topics.
Knowledge sharing among universities is limited.
Project supervision and monitoring processes are inefficient.
Retrieval of archived projects is time-consuming.

These challenges create a need for a centralized and digital platform capable of securely managing academic projects and research papers while providing easy access to authorized users.

Aim of the Project

To develop a web-based Final Year Project and Research Management System that provides a centralized platform for managing, storing, searching, and accessing final year projects and research papers from universities across Tanzania.

Objectives
General Objective

To design and implement a centralized web-based repository for academic projects and research papers.

Specific Objectives
To provide secure user registration and authentication.
To enable students to upload final year projects and research papers.
To facilitate project review and approval by supervisors.
To provide advanced search and filtering capabilities.
To maintain a centralized repository of academic resources.
To reduce duplication of research topics.
To improve accessibility of research outputs.
To generate reports and statistics for administrators.
To support knowledge sharing among universities.
To preserve academic documents in digital format.

System Scope

The system focuses on managing final year projects and research papers from higher learning institutions. It covers:

Included
Student registration and login.
Supervisor login and project review.
Administrator management functions.
Project upload and management.
Research paper upload and management.
Search and retrieval of academic documents.
Report generation.
University management.
Excluded
Financial management.
Examination management.
Course registration.
Student grading systems.
Learning Management System (LMS) functions.

System Users
1. Administrator

The administrator has full control over the system.

Responsibilities
Manage user accounts.
Manage universities.
Manage projects.
Manage research papers.
Approve or remove content.
Generate reports.
Monitor system activities.
Privileges
Create, update, and delete records.
View system statistics.
Manage all uploaded documents.

2. Student

Students are the primary contributors of projects and research papers.

Responsibilities
Register an account.
Login securely.
Upload project documents.
Upload research papers.
Update personal information.
Search and view repository contents.
Privileges
Access own uploads.
Download approved documents.
Edit personal submissions.

3. Supervisor

Supervisors are responsible for reviewing academic submissions.

Responsibilities
Review submitted projects.
Approve or reject projects.
Provide feedback.
Search project repository.
Privileges
Access projects under review.
Monitor student submissions.

Functional Requirements

The system shall:

User Management
Register users.
Authenticate users.
Manage user roles.
Reset passwords.
Project Management
Upload project files.
View projects.
Edit project details.
Delete projects.
Approve projects.
Research Management
Upload research papers.
View research papers.
Search research records.
Download approved files.
Search Module

Search by:

Project title.
Author name.
University.
Department.
Academic year.
Keywords.
Reporting Module

Generate reports for:

Number of projects.
Number of research papers.
Projects per university.
Research papers per university.
Registered users.

Non-Functional Requirements
Security
Password hashing.
Session management.
Input validation.
File upload validation.
Performance
Fast search results.
Efficient database queries.
Quick document retrieval.
Reliability
Consistent system availability.
Accurate data storage.
Usability
User-friendly interface.
Responsive design.
Easy navigation.
Maintainability
Modular code structure.
Well-documented source code.

System Architecture
Users
   │
   ▼
Web Interface (XHTML, CSS, JavaScript)
   │
   ▼
PHP Application Layer
   │
   ▼
MySQL Database

The presentation layer handles user interaction, the application layer processes business logic, and the database layer stores all system records and documents.

Technology Stack
Frontend
XHTML
CSS3
JavaScript
Backend
PHP
Database
MySQL
Development Environment
XAMPP
Browser Support
Google Chrome
Mozilla Firefox
Microsoft Edge
Opera

Database Design
Users Table

Stores account information.

Fields:

id
fullname
email
phone
university
password
role
created_at

Universities Table

Stores university information.

Fields:

id
university_name

Projects Table

Stores project details.

Fields:

id
title
student_name
university
department
academic_year
description
file_name
status
created_at

Research Table

Stores research paper information.

Fields:

id
title
author
university
year
abstract
file_name
created_at

**Main Features**
Authentication System

Secure registration and login for all users.

Project Repository

Central storage of final year projects.

Research Repository

Central storage of research papers.

Advanced Search

Powerful search and filtering tools.

Document Downloads

Download approved project and research files.

Reporting Dashboard

Statistical reports and summaries.

University Management

Management of participating universities.

**Expected Benefits**
**For Students**
Access previous projects.
Avoid topic duplication.
Obtain research references.
**For Supervisors**
Simplify project monitoring.
Improve review efficiency.
**For Universities**
Preserve academic work.
Improve research visibility.
Enhance institutional knowledge management.
**For Researchers**
Easy access to academic resources.
Support literature review activities.

FUTURE ENHANCEMENTS

Integration with university systems.
Artificial Intelligence-based project recommendations.
Plagiarism detection module.
Online supervisor feedback system.
Email notifications.
Multi-university collaboration portal.
Mobile application support.
Cloud storage integration.
Research analytics dashboard.

CONCLUSION

The Final Year Project and Research Management System (FYPRMS) provides a comprehensive solution for managing and preserving academic projects and research papers in Tanzania. By creating a centralized digital repository, the system improves accessibility, enhances knowledge sharing, reduces duplication of research topics, and supports the long-term preservation of academic resources. The platform empowers students, supervisors, researchers, and administrators to efficiently manage research outputs while contributing to the advancement of education and innovation across universities in Tanzania.
