-- Add assigned_staff_id column to appointments table
-- Run this SQL script to add the column for storing assigned stylist

ALTER TABLE appointments ADD COLUMN assigned_staff_id VARCHAR(50) NULL AFTER style;

