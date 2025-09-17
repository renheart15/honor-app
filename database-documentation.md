# CTU Honor Application System - Database Documentation

## Overview
This document provides comprehensive information about the database structure, configuration, and usage for the CTU Honor Application System.

## Database Information
- **Database Name**: `honor_app`
- **Engine**: InnoDB
- **Character Set**: utf8mb4
- **Collation**: utf8mb4_unicode_ci
- **Estimated Size**: ~50MB (with sample data)

## Table Structure Summary

### 1. Core Tables
| Table Name | Purpose | Records (Est.) |
|------------|---------|----------------|
| `users` | User accounts (students, advisers, chairpersons) | 1,000+ |
| `academic_periods` | Semester/academic year management | 20+ |
| `grade_submissions` | Uploaded grade reports | 500+ |
| `grades` | Individual subject grades | 5,000+ |
| `gwa_calculations` | Computed GWA results | 500+ |
| `honor_applications` | Honor applications (Dean's List, etc.) | 200+ |
| `notifications` | System notifications | 2,000+ |
| `system_settings` | Configuration settings | 50+ |
| `honor_rankings` | Honor roll rankings | 1,000+ |
| `audit_logs` | System activity logs | 10,000+ |

### 2. Database Views
| View Name | Purpose |
|-----------|---------|
| `student_gwa_summary` | Student GWA overview with honor classification |
| `honor_applications_detailed` | Detailed honor applications with user info |

### 3. Stored Procedures
| Procedure Name | Purpose | Parameters |
|----------------|---------|------------|
| `CalculateGWA` | Compute student GWA | `p_submission_id INT` |
| `GenerateHonorRankings` | Generate honor rankings | `p_academic_period_id INT, p_department VARCHAR(100)` |

## Key Relationships

### User Management
\`\`\`
users (1) ←→ (M) grade_submissions
users (1) ←→ (M) gwa_calculations  
users (1) ←→ (M) honor_applications
users (1) ←→ (M)
