-- Update problem_updates table if needed
DROP TABLE IF EXISTS problem_updates;
CREATE TABLE problem_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    problem_id INT NOT NULL,
    updated_by INT NOT NULL,
    update_type VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    notes TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE CASCADE
);