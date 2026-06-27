-- Backfill missing attendance summary rows for employees across discovered periods.
-- Safe behavior: inserts only missing rows, does not update or delete existing rows.

INSERT INTO employee_attendance_summary (
    corpId,
    empCode,
    companyName,
    totalPresent,
    workingDays,
    holidays,
    weekOff,
    `leave`,
    paidDays,
    absent,
    month,
    year,
    created_at,
    updated_at
)
SELECT
    e.corp_id AS corpId,
    e.EmpCode AS empCode,
    e.company_name AS companyName,
    0 AS totalPresent,
    0 AS workingDays,
    0 AS holidays,
    0 AS weekOff,
    0 AS `leave`,
    0 AS paidDays,
    0 AS absent,
    p.month,
    p.year,
    NOW() AS created_at,
    NOW() AS updated_at
FROM employment_details e
INNER JOIN (
    SELECT DISTINCT corpId AS corp_id, companyName AS company_name, month, year
    FROM employee_attendance_summary
    UNION
    SELECT DISTINCT
        corpId AS corp_id,
        companyName AS company_name,
        DATE_FORMAT(`date`, '%M') AS month,
        CAST(YEAR(`date`) AS CHAR(4)) AS year
    FROM attendances
) p
    ON p.corp_id = e.corp_id
     AND p.company_name = e.company_name
LEFT JOIN employee_attendance_summary s
    ON s.corpId = e.corp_id
   AND s.empCode = e.EmpCode
     AND s.companyName = e.company_name
   AND s.month = p.month
   AND s.year = p.year
WHERE e.EmpCode IS NOT NULL
    AND e.ActiveYn = 1
  AND TRIM(e.EmpCode) <> ''
  AND s.id IS NULL;
