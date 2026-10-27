-- Allow blank result components without converting existing scores.
ALTER TABLE results
    MODIFY ca1 DECIMAL(5,2) NULL DEFAULT NULL,
    MODIFY ca2 DECIMAL(5,2) NULL DEFAULT NULL,
    MODIFY exam DECIMAL(5,2) NULL DEFAULT NULL;
