-- ========================================
-- dim_time
-- CALENDAR & SHIFT DIMENSION TABLE
-- ========================================
-- Purpose: Time dimension for Superset date analysis
-- Use Cases:
--   - Weekday vs weekend comparison
--   - Shift performance analysis
--   - Holiday surge analysis
--   - Calendar-based filtering
-- Optimized for: Apache Superset, PowerBI, Tableau

-- Note: This generates a time dimension table for the last 5 years and next 2 years
-- Adjust date range as needed for your requirements

CREATE TABLE IF NOT EXISTS dim_time (
    date_key DATE PRIMARY KEY,
    date DATE NOT NULL,
    year INT NOT NULL,
    quarter INT NOT NULL,
    month INT NOT NULL,
    month_name VARCHAR(20) NOT NULL,
    month_name_short VARCHAR(3) NOT NULL,
    week_of_year INT NOT NULL,
    day INT NOT NULL,
    day_of_week INT NOT NULL,  -- 0=Sunday, 6=Saturday
    day_name VARCHAR(20) NOT NULL,
    day_name_short VARCHAR(3) NOT NULL,
    is_weekend BOOLEAN NOT NULL,
    is_weekday BOOLEAN NOT NULL,
    public_holiday_flag BOOLEAN DEFAULT FALSE,
    holiday_name VARCHAR(100),
    shift_name VARCHAR(50),
    shift_start TIME,
    shift_end TIME,
    fiscal_year INT,
    fiscal_quarter INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_dim_time_date ON dim_time(date);
CREATE INDEX IF NOT EXISTS idx_dim_time_year_month ON dim_time(year, month);
CREATE INDEX IF NOT EXISTS idx_dim_time_day_of_week ON dim_time(day_of_week);
CREATE INDEX IF NOT EXISTS idx_dim_time_is_weekend ON dim_time(is_weekend);
CREATE INDEX IF NOT EXISTS idx_dim_time_public_holiday ON dim_time(public_holiday_flag);

-- Populate dimension table with date range
INSERT INTO dim_time (
    date_key, date, year, quarter, month, month_name, month_name_short,
    week_of_year, day, day_of_week, day_name, day_name_short,
    is_weekend, is_weekday, shift_name, shift_start, shift_end,
    fiscal_year, fiscal_quarter
)
SELECT
    d::date as date_key,
    d::date as date,
    EXTRACT(YEAR FROM d)::int as year,
    EXTRACT(QUARTER FROM d)::int as quarter,
    EXTRACT(MONTH FROM d)::int as month,
    TO_CHAR(d, 'Month') as month_name,
    TO_CHAR(d, 'Mon') as month_name_short,
    EXTRACT(WEEK FROM d)::int as week_of_year,
    EXTRACT(DAY FROM d)::int as day,
    EXTRACT(DOW FROM d)::int as day_of_week,
    CASE EXTRACT(DOW FROM d)::int
        WHEN 0 THEN 'Sunday'
        WHEN 1 THEN 'Monday'
        WHEN 2 THEN 'Tuesday'
        WHEN 3 THEN 'Wednesday'
        WHEN 4 THEN 'Thursday'
        WHEN 5 THEN 'Friday'
        WHEN 6 THEN 'Saturday'
    END as day_name,
    CASE EXTRACT(DOW FROM d)::int
        WHEN 0 THEN 'Sun'
        WHEN 1 THEN 'Mon'
        WHEN 2 THEN 'Tue'
        WHEN 3 THEN 'Wed'
        WHEN 4 THEN 'Thu'
        WHEN 5 THEN 'Fri'
        WHEN 6 THEN 'Sat'
    END as day_name_short,
    EXTRACT(DOW FROM d)::int IN (0, 6) as is_weekend,
    EXTRACT(DOW FROM d)::int NOT IN (0, 6) as is_weekday,
    -- Default shift assignment (can be updated based on actual shifts)
    CASE 
        WHEN EXTRACT(DOW FROM d)::int IN (0, 6) THEN 'Weekend Shift'
        ELSE 'Regular Shift'
    END as shift_name,
    '08:00:00'::time as shift_start,
    '17:00:00'::time as shift_end,
    -- Fiscal year (assuming Malaysia fiscal year starts in January)
    EXTRACT(YEAR FROM d)::int as fiscal_year,
    EXTRACT(QUARTER FROM d)::int as fiscal_quarter
FROM generate_series(
    CURRENT_DATE - INTERVAL '5 years',  -- Start: 5 years ago
    CURRENT_DATE + INTERVAL '2 years',  -- End: 2 years in future
    INTERVAL '1 day'
) d
ON CONFLICT (date_key) DO NOTHING;

-- === UPDATE PUBLIC HOLIDAYS (MALAYSIA) ===
-- Note: Update this annually with actual public holiday dates
-- This is a sample for common Malaysian holidays - adjust as needed

UPDATE dim_time SET 
    public_holiday_flag = TRUE,
    holiday_name = 'New Year''s Day'
WHERE month = 1 AND day = 1;

UPDATE dim_time SET 
    public_holiday_flag = TRUE,
    holiday_name = 'Federal Territory Day'
WHERE month = 2 AND day = 1;

UPDATE dim_time SET 
    public_holiday_flag = TRUE,
    holiday_name = 'Labour Day'
WHERE month = 5 AND day = 1;

UPDATE dim_time SET 
    public_holiday_flag = TRUE,
    holiday_name = 'Malaysia Day'
WHERE month = 9 AND day = 16;

UPDATE dim_time SET 
    public_holiday_flag = TRUE,
    holiday_name = 'National Day'
WHERE month = 8 AND day = 31;

-- Agong's Birthday (First Saturday of June)
UPDATE dim_time SET 
    public_holiday_flag = TRUE,
    holiday_name = 'Agong''s Birthday'
WHERE month = 6 
  AND day_of_week = 6  -- Saturday
  AND day <= 7;

-- Christmas Day
UPDATE dim_time SET 
    public_holiday_flag = TRUE,
    holiday_name = 'Christmas Day'
WHERE month = 12 AND day = 25;

-- === FLOATING HOLIDAYS ===
-- Note: These need to be updated annually based on lunar calendar
-- Examples for 2025-2026 (adjust based on actual dates):

-- Chinese New Year 2025 (Jan 29-30)
UPDATE dim_time SET 
    public_holiday_flag = TRUE,
    holiday_name = 'Chinese New Year'
WHERE date IN ('2025-01-29', '2025-01-30');

-- Chinese New Year 2026 (Feb 17-18)
UPDATE dim_time SET 
    public_holiday_flag = TRUE,
    holiday_name = 'Chinese New Year'
WHERE date IN ('2026-02-17', '2026-02-18');

-- Hari Raya Aidilfitri (adjust based on Islamic calendar)
-- Example for 2025 (March 30-31)
UPDATE dim_time SET 
    public_holiday_flag = TRUE,
    holiday_name = 'Hari Raya Aidilfitri'
WHERE date IN ('2025-03-30', '2025-03-31');

-- Hari Raya Haji (adjust based on Islamic calendar)
-- Example for 2025 (June 7)
UPDATE dim_time SET 
    public_holiday_flag = TRUE,
    holiday_name = 'Hari Raya Haji'
WHERE date = '2025-06-07';

-- Deepavali (adjust based on Hindu calendar)
-- Example for 2025 (October 20)
UPDATE dim_time SET 
    public_holiday_flag = TRUE,
    holiday_name = 'Deepavali'
WHERE date = '2025-10-20';

-- === UPDATE SHIFT DEFINITIONS ===
-- Customize based on your actual shift schedules

-- Morning Shift (7am - 3pm)
UPDATE dim_time SET 
    shift_name = 'Morning Shift',
    shift_start = '07:00:00'::time,
    shift_end = '15:00:00'::time
WHERE is_weekday = TRUE 
  AND date IN (
      -- Define specific dates for morning shift if rotating
      SELECT date FROM dim_time WHERE is_weekday = TRUE
  );

-- Afternoon Shift (3pm - 11pm) - Can be updated for specific agents/dates
-- Evening Shift (11pm - 7am) - Night shift

-- === CREATE VIEW FOR EASY QUERYING ===
CREATE OR REPLACE VIEW v_dim_time AS
SELECT
    date_key,
    date,
    year,
    quarter,
    month,
    month_name,
    month_name_short,
    week_of_year,
    day,
    day_of_week,
    day_name,
    day_name_short,
    is_weekend,
    is_weekday,
    public_holiday_flag,
    holiday_name,
    shift_name,
    shift_start,
    shift_end,
    fiscal_year,
    fiscal_quarter,
    
    -- Additional computed fields
    CASE 
        WHEN public_holiday_flag THEN 'Public Holiday'
        WHEN is_weekend THEN 'Weekend'
        ELSE 'Weekday'
    END as day_type,
    
    -- Week classification
    CASE 
        WHEN week_of_year <= 13 THEN 'Q1'
        WHEN week_of_year <= 26 THEN 'Q2'
        WHEN week_of_year <= 39 THEN 'Q3'
        ELSE 'Q4'
    END as quarter_label,
    
    -- Month classification
    CASE 
        WHEN month IN (12, 1, 2) THEN 'Winter'
        WHEN month IN (3, 4, 5) THEN 'Spring'
        WHEN month IN (6, 7, 8) THEN 'Summer'
        ELSE 'Fall'
    END as season,
    
    -- Working day flag (weekday + not holiday)
    CASE 
        WHEN is_weekday = TRUE AND public_holiday_flag = FALSE THEN TRUE
        ELSE FALSE
    END as is_working_day,
    
    created_at

FROM dim_time;

-- === SAMPLE QUERIES FOR SUPERSET ===
/*

-- 1. Weekday vs Weekend comparison
SELECT 
    dt.day_type,
    COUNT(DISTINCT vci.call_id) as total_calls,
    AVG(vci.talk_time_sec) as avg_duration_sec
FROM v_call_interaction vci
JOIN v_dim_time dt ON vci.call_date = dt.date
WHERE vci.call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY dt.day_type;

-- 2. Shift performance
SELECT 
    dt.shift_name,
    COUNT(DISTINCT vci.call_id) as total_calls,
    AVG(vci.queue_wait_time_sec) as avg_wait_time_sec
FROM v_call_interaction vci
JOIN v_dim_time dt ON vci.call_date = dt.date
WHERE vci.call_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY dt.shift_name;

-- 3. Holiday surge analysis
SELECT 
    dt.date,
    dt.holiday_name,
    COUNT(DISTINCT vci.call_id) as total_calls,
    COUNT(DISTINCT CASE WHEN vci.is_abandoned = 1 THEN vci.call_id END) as abandoned_calls
FROM v_call_interaction vci
JOIN v_dim_time dt ON vci.call_date = dt.date
WHERE dt.public_holiday_flag = TRUE
  AND vci.call_date >= CURRENT_DATE - INTERVAL '12 months'
GROUP BY dt.date, dt.holiday_name
ORDER BY dt.date DESC;

-- 4. Monthly trend with holidays
SELECT 
    dt.year,
    dt.month_name,
    COUNT(DISTINCT CASE WHEN dt.public_holiday_flag THEN dt.date END) as holiday_days,
    COUNT(DISTINCT CASE WHEN dt.is_working_day THEN dt.date END) as working_days,
    COUNT(DISTINCT vci.call_id) as total_calls
FROM v_dim_time dt
LEFT JOIN v_call_interaction vci ON vci.call_date = dt.date
WHERE dt.date >= CURRENT_DATE - INTERVAL '12 months'
GROUP BY dt.year, dt.month, dt.month_name
ORDER BY dt.year, dt.month;

-- 5. Day of week pattern
SELECT 
    dt.day_name,
    dt.day_of_week,
    COUNT(DISTINCT vci.call_id) as avg_daily_calls,
    AVG(vci.talk_time_sec) as avg_call_duration_sec
FROM v_call_interaction vci
JOIN v_dim_time dt ON vci.call_date = dt.date
WHERE vci.call_date >= CURRENT_DATE - INTERVAL '90 days'
GROUP BY dt.day_name, dt.day_of_week
ORDER BY dt.day_of_week;

*/
