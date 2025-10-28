<?php
// COMPLETE Working Medical Functions - Guaranteed Database Updates

function saveMedicalResponseActual($problem_id, $doctor_id, $response, $recommendations = '', $follow_up_required = 0, $urgency_level = 'medium') {
    try {
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();

        // Validate inputs
        if (empty($response) || empty($problem_id) || empty($doctor_id)) {
            throw new Exception("Required fields missing: problem_id, doctor_id, or response");
        }

        // Insert medical response using available columns in current DB
        $result = false;
        $columnsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medical_responses'");
        $columnsStmt->execute();
        $availableColumns = array_map('strtolower', array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));

        $fields = ['problem_id', 'doctor_id', 'response'];
        $placeholders = ['?', '?', '?'];
        $values = [(int)$problem_id, (int)$doctor_id, $response];

        if (in_array('recommendations', $availableColumns)) {
            $fields[] = 'recommendations';
            $placeholders[] = '?';
            $values[] = $recommendations;
        } elseif (in_array('advice', $availableColumns)) {
            $fields[] = 'advice';
            $placeholders[] = '?';
            $values[] = $recommendations;
        }

        if (in_array('follow_up_required', $availableColumns)) {
            $fields[] = 'follow_up_required';
            $placeholders[] = '?';
            $values[] = (int)$follow_up_required;
        } elseif (in_array('follow_up_needed', $availableColumns)) {
            $fields[] = 'follow_up_needed';
            $placeholders[] = '?';
            $values[] = (int)$follow_up_required;
        }

        if (in_array('urgency_level', $availableColumns)) {
            $fields[] = 'urgency_level';
            $placeholders[] = '?';
            $values[] = $urgency_level;
        }

        if (in_array('status', $availableColumns)) {
            $fields[] = 'status';
            $placeholders[] = '?';
            $values[] = 'submitted';
        }

        // timestamps
        if (in_array('created_at', $availableColumns)) {
            $fields[] = 'created_at';
            $placeholders[] = 'NOW()';
        }
        if (in_array('updated_at', $availableColumns)) {
            $fields[] = 'updated_at';
            $placeholders[] = 'NOW()';
        }

        $sql = 'INSERT INTO medical_responses (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);

        if (!$result) {
            throw new Exception("Failed to insert medical response");
        }

        $response_id = $pdo->lastInsertId();
        if (!$response_id) {
            throw new Exception("Medical response inserted but no ID returned");
        }

        // Update problem's updated_at timestamp
        $stmt = $pdo->prepare("UPDATE problems SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$problem_id]);

        // Get problem and user details for notifications
        $stmt = $pdo->prepare("
            SELECT p.title, p.villager_id, p.assigned_to, p.priority,
                   v.name as villager_name, v.email as villager_email,
                   a.name as avms_name, a.email as avms_email,
                   d.name as doctor_name, d.email as doctor_email
            FROM problems p 
            LEFT JOIN users v ON p.villager_id = v.id
            LEFT JOIN users a ON p.assigned_to = a.id
            LEFT JOIN users d ON d.id = ?
            WHERE p.id = ?
        ");
        $stmt->execute([$doctor_id, $problem_id]);
        $details = $stmt->fetch();

        if (!$details) {
            throw new Exception("Could not retrieve problem details for notifications");
        }

        // Determine available columns for notifications to prevent schema mismatch issues
        $notificationColumnsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'");
        $notificationColumnsStmt->execute();
        $notificationColumns = array_map('strtolower', array_column($notificationColumnsStmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));

        $sendNotification = function ($userId, $title, $message, $typeValue = null, $priorityValue = null) use ($pdo, $notificationColumns, $problem_id) {
            if (!$userId || !in_array('user_id', $notificationColumns, true)) {
                return false;
            }

            $fields = ['user_id'];
            $placeholders = ['?'];
            $values = [(int)$userId];

            if (in_array('problem_id', $notificationColumns, true)) {
                $fields[] = 'problem_id';
                $placeholders[] = '?';
                $values[] = (int)$problem_id;
            }

            if (in_array('title', $notificationColumns, true) && $title !== null) {
                $fields[] = 'title';
                $placeholders[] = '?';
                $values[] = $title;
            }

            if (in_array('message', $notificationColumns, true) && $message !== null) {
                $fields[] = 'message';
                $placeholders[] = '?';
                $values[] = $message;
            }

            if ($typeValue !== null && in_array('type', $notificationColumns, true)) {
                $fields[] = 'type';
                $placeholders[] = '?';
                $values[] = $typeValue;
            }

            if ($priorityValue !== null && in_array('priority', $notificationColumns, true)) {
                $fields[] = 'priority';
                $placeholders[] = '?';
                $values[] = $priorityValue;
            }

            if (in_array('is_read', $notificationColumns, true)) {
                $fields[] = 'is_read';
                $placeholders[] = '?';
                $values[] = 0;
            }

            if (in_array('created_at', $notificationColumns, true)) {
                $fields[] = 'created_at';
                $placeholders[] = 'NOW()';
            }

            if (empty($fields)) {
                return false;
            }

            $sql = 'INSERT INTO notifications (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($values);
        };

        // Create comprehensive notifications
        $notifications_created = 0;

        if (!empty($details['assigned_to']) && $sendNotification(
                $details['assigned_to'],
                'Medical Response Available - ' . $details['title'],
                'Dr. ' . $details['doctor_name'] . ' has provided comprehensive medical guidance for this case. Please review the response and coordinate patient care accordingly.',
                'info',
                'high'
            )) {
            $notifications_created++;
        }

        if (!empty($details['villager_id']) && $sendNotification(
                $details['villager_id'],
                'Medical Consultation Completed',
                'A qualified doctor has reviewed your medical case and provided professional guidance. Please coordinate with your ANMS officer for next steps.',
                'success',
                'high'
            )) {
            $notifications_created++;
        }

        if ($sendNotification(
                $doctor_id,
                'Medical Response Successfully Submitted',
                'Your medical response has been recorded and all relevant parties have been notified. Response ID: ' . $response_id,
                'success',
                'medium'
            )) {
            $notifications_created++;
        }

        // Add to audit trail (match existing problem_updates schema)
        $stmt = $pdo->prepare("
            INSERT INTO problem_updates (problem_id, updated_by, update_type, old_value, new_value, notes, timestamp) 
            VALUES (?, ?, 'response', NULL, 'response_added', ?, NOW())
        ");
        $stmt->execute([
            $problem_id,
            $doctor_id,
            'Medical consultation completed by Dr. ' . $details['doctor_name'] . '. Urgency level: ' . $urgency_level . '. Follow-up required: ' . ($follow_up_required ? 'Yes' : 'No')
        ]);

        $pdo->commit();

        return [
            'success' => true,
            'response_id' => $response_id,
            'notifications_created' => $notifications_created,
            'message' => 'Medical response saved successfully. ' . $notifications_created . ' notifications sent to relevant parties.',
            'details' => [
                'problem_title' => $details['title'],
                'urgency_level' => $urgency_level,
                'follow_up_required' => $follow_up_required,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];

    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }

        error_log("MEDICAL RESPONSE SAVE ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'message' => 'Failed to save medical response: ' . $e->getMessage()
        ];
    }
}

function getDashboardStatsActual($doctor_id) {
    try {
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get comprehensive dashboard statistics
        $stats = [];

        // Total escalated cases
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE status = 'escalated'");
        $stmt->execute();
        $stats['total_escalated'] = (int)$stmt->fetchColumn();

        // Urgent cases  
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE status = 'escalated' AND priority = 'urgent'");
        $stmt->execute();
        $stats['urgent_cases'] = (int)$stmt->fetchColumn();

        // High priority cases
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE status = 'escalated' AND priority = 'high'");
        $stmt->execute();
        $stats['high_priority'] = (int)$stmt->fetchColumn();

        // My total responses
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_responses WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        $stats['my_responses'] = (int)$stmt->fetchColumn();

        // Unique cases I helped
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT p.id) 
            FROM problems p 
            INNER JOIN medical_responses mr ON p.id = mr.problem_id 
            WHERE mr.doctor_id = ?
        ");
        $stmt->execute([$doctor_id]);
        $stats['cases_helped'] = (int)$stmt->fetchColumn();

        // Today's responses
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM medical_responses 
            WHERE doctor_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$doctor_id]);
        $stats['responses_today'] = (int)$stmt->fetchColumn();

        // This week's responses
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM medical_responses 
            WHERE doctor_id = ? AND YEARWEEK(created_at) = YEARWEEK(NOW())
        ");
        $stmt->execute([$doctor_id]);
        $stats['responses_this_week'] = (int)$stmt->fetchColumn();

        // Cases requiring follow-up
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM medical_responses 
            WHERE doctor_id = ? AND follow_up_required = 1 
            AND problem_id NOT IN (
                SELECT DISTINCT problem_id FROM medical_responses 
                WHERE doctor_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND follow_up_required = 0
            )
        ");
        $stmt->execute([$doctor_id, $doctor_id]);
        $stats['follow_up_cases'] = (int)$stmt->fetchColumn();

        // Unassigned escalated cases
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE status = 'escalated' AND escalated_to IS NULL");
        $stmt->execute();
        $stats['unassigned_cases'] = (int)$stmt->fetchColumn();

        // Recent activity (last 24 hours)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM problems 
            WHERE status = 'escalated' AND updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $stats['recent_escalations'] = (int)$stmt->fetchColumn();

        return array_merge($stats, [
            'last_updated' => date('Y-m-d H:i:s'),
            'doctor_id' => $doctor_id,
            'system_status' => 'active'
        ]);

    } catch (Exception $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
        return [
            'total_escalated' => 0,
            'urgent_cases' => 0,
            'high_priority' => 0,
            'my_responses' => 0,
            'cases_helped' => 0,
            'responses_today' => 0,
            'responses_this_week' => 0,
            'follow_up_cases' => 0,
            'unassigned_cases' => 0,
            'recent_escalations' => 0,
            'last_updated' => date('Y-m-d H:i:s'),
            'error' => $e->getMessage(),
            'system_status' => 'error'
        ];
    }
}

function getEscalatedCasesActual($priority_filter = null, $search = null, $limit = null, $doctor_id = null) {
    try {
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "
            SELECT p.*, 
                   v.name as villager_name, v.phone as villager_phone, v.village as villager_village, v.email as villager_email,
                   a.name as avms_name, a.phone as avms_phone, a.email as avms_email,
                   COUNT(mr.id) as response_count,
                   MAX(mr.created_at) as last_response_date,
                   MAX(mr.urgency_level) as highest_response_urgency,
                   GROUP_CONCAT(DISTINCT u.name ORDER BY mr.created_at DESC SEPARATOR ', ') as responding_doctors,
                   CASE 
                       WHEN p.priority = 'urgent' THEN 1
                       WHEN p.priority = 'high' THEN 2
                       WHEN p.priority = 'medium' THEN 3
                       ELSE 4
                   END as priority_sort
            FROM problems p 
            INNER JOIN users v ON p.villager_id = v.id 
            LEFT JOIN users a ON p.assigned_to = a.id
            LEFT JOIN medical_responses mr ON p.id = mr.problem_id
            LEFT JOIN users u ON mr.doctor_id = u.id
            WHERE p.status = 'escalated'
        ";

        $params = [];

        if ($priority_filter && $priority_filter !== 'all') {
            $sql .= " AND p.priority = ?";
            $params[] = $priority_filter;
        }

        if ($search && trim($search) !== '') {
            $sql .= " AND (p.title LIKE ? OR p.description LIKE ? OR v.name LIKE ? OR p.category LIKE ?)";
            $searchTerm = "%" . trim($search) . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($doctor_id) {
            // Show cases assigned to this doctor or unassigned cases
            $sql .= " AND (p.escalated_to = ? OR p.escalated_to IS NULL)";
            $params[] = $doctor_id;
        }

        $sql .= " GROUP BY p.id ORDER BY priority_sort ASC, p.updated_at DESC";

        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add additional metadata to each case
        foreach ($cases as &$case) {
            // Calculate time since escalation
            $escalation_time = strtotime($case['escalation_date'] ?? $case['updated_at']);
            $case['hours_since_escalation'] = $escalation_time ? floor((time() - $escalation_time) / 3600) : 0;

            // Determine urgency status
            $case['is_urgent'] = ($case['priority'] === 'urgent') || 
                                ($case['priority'] === 'high' && $case['hours_since_escalation'] > 4) ||
                                ($case['hours_since_escalation'] > 24);

            // Response status
            $case['needs_response'] = ($case['response_count'] == 0) || 
                                    ($case['last_response_date'] && strtotime($case['last_response_date']) < strtotime('-48 hours'));

            // Risk level assessment
            if ($case['priority'] === 'urgent' && $case['response_count'] == 0) {
                $case['risk_level'] = 'critical';
            } elseif ($case['priority'] === 'urgent' || ($case['priority'] === 'high' && $case['hours_since_escalation'] > 2)) {
                $case['risk_level'] = 'high';
            } elseif ($case['hours_since_escalation'] > 12) {
                $case['risk_level'] = 'medium';
            } else {
                $case['risk_level'] = 'low';
            }
        }

        return $cases;

    } catch (Exception $e) {
        error_log("Get escalated cases error: " . $e->getMessage());
        return [];
    }
}

function getMyResponsesActual($doctor_id, $limit = null, $include_details = true) {
    try {
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "
            SELECT mr.*, 
                   p.title as problem_title, p.priority, p.status as problem_status, p.category,
                   v.name as villager_name, v.village as villager_village, v.phone as villager_phone,
                   a.name as avms_name, a.phone as avms_phone
        ";

        if ($include_details) {
            $sql .= ", p.description as problem_description";
        }

        $sql .= "
            FROM medical_responses mr
            INNER JOIN problems p ON mr.problem_id = p.id
            INNER JOIN users v ON p.villager_id = v.id
            LEFT JOIN users a ON p.assigned_to = a.id
            WHERE mr.doctor_id = ?
            ORDER BY mr.created_at DESC
        ";

        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$doctor_id]);
        $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add metadata to responses
        foreach ($responses as &$response) {
            $response['days_ago'] = floor((time() - strtotime($response['created_at'])) / 86400);
            $response['response_summary'] = substr($response['response'], 0, 200) . (strlen($response['response']) > 200 ? '...' : '');
        }

        return $responses;

    } catch (Exception $e) {
        error_log("Get my responses error: " . $e->getMessage());
        return [];
    }
}

function getNotificationsActual($user_id, $limit = 10, $mark_as_read = false) {
    try {
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "
            SELECT n.*, p.title as problem_title, p.priority as problem_priority
            FROM notifications n
            LEFT JOIN problems p ON n.problem_id = p.id
            WHERE n.user_id = ?
            ORDER BY n.priority DESC, n.created_at DESC
        ";

        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($mark_as_read && !empty($notifications)) {
            $notification_ids = array_column($notifications, 'id');
            $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id IN ($placeholders)");
            $stmt->execute($notification_ids);
        }

        // Add time metadata
        foreach ($notifications as &$notification) {
            $time_diff = time() - strtotime($notification['created_at']);
            if ($time_diff < 60) {
                $notification['time_ago'] = 'Just now';
            } elseif ($time_diff < 3600) {
                $notification['time_ago'] = floor($time_diff / 60) . ' minutes ago';
            } elseif ($time_diff < 86400) {
                $notification['time_ago'] = floor($time_diff / 3600) . ' hours ago';
            } else {
                $notification['time_ago'] = floor($time_diff / 86400) . ' days ago';
            }
        }

        return $notifications;

    } catch (Exception $e) {
        error_log("Get notifications error: " . $e->getMessage());
        return [];
    }
}

function getUnreadNotificationCountActual($user_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Get unread notification count error: " . $e->getMessage());
        return 0;
    }
}

function updateDoctorProfileActual($user_id, $profile_data) {
    try {
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();

        // Update basic user info
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, phone = ?, updated_at = NOW() 
            WHERE id = ? AND role = 'doctor'
        ");
        $stmt->execute([
            $profile_data['name'],
            $profile_data['phone'],
            $user_id
        ]);

        // Update additional doctor info with comprehensive data
        $additional_info = [
            'specialization' => $profile_data['specialization'] ?? '',
            'license_number' => $profile_data['license_number'] ?? '',
            'qualifications' => $profile_data['qualifications'] ?? '',
            'experience_years' => (int)($profile_data['experience_years'] ?? 0),
            'hospital_affiliation' => $profile_data['hospital_affiliation'] ?? '',
            'consultation_hours' => $profile_data['consultation_hours'] ?? '',
            'emergency_contact' => $profile_data['emergency_contact'] ?? '',
            'bio' => $profile_data['bio'] ?? '',
            'languages' => $profile_data['languages'] ?? '',
            'updated_at' => date('Y-m-d H:i:s'),
            'profile_completed' => !empty($profile_data['specialization']) && 
                                 !empty($profile_data['qualifications']) && 
                                 !empty($profile_data['hospital_affiliation'])
        ];

        $stmt = $pdo->prepare("
            UPDATE users 
            SET additional_info = ? 
            WHERE id = ? AND role = 'doctor'
        ");
        $stmt->execute([
            json_encode($additional_info),
            $user_id
        ]);

        // Create audit log
        $stmt = $pdo->prepare("
            INSERT INTO problem_updates (problem_id, updated_by, update_type, new_value, notes, timestamp) 
            VALUES (0, ?, 'profile_update', 'profile_updated', ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            'Doctor profile updated: ' . $profile_data['name']
        ]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Doctor profile updated successfully',
            'profile_completed' => $additional_info['profile_completed']
        ];

    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("Update doctor profile error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to update profile: ' . $e->getMessage()
        ];
    }
}

function getDoctorProfileActual($user_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT id, name, email, phone, role, additional_info, created_at, updated_at, last_login, status
            FROM users 
            WHERE id = ? AND role = 'doctor'
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['additional_info']) {
            $additional = json_decode($user['additional_info'], true);
            if ($additional) {
                $user = array_merge($user, $additional);
            }
        }

        // Add profile completion percentage
        if ($user) {
            $required_fields = ['name', 'phone', 'specialization', 'qualifications', 'hospital_affiliation'];
            $completed_fields = 0;
            foreach ($required_fields as $field) {
                if (!empty($user[$field])) {
                    $completed_fields++;
                }
            }
            $user['profile_completion'] = round(($completed_fields / count($required_fields)) * 100);
        }

        return $user;

    } catch (Exception $e) {
        error_log("Get doctor profile error: " . $e->getMessage());
        return null;
    }
}

function initializeAllTablesActual() {
    try {
        $pdo = getDBConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // This function is now handled by the comprehensive database setup
        // Just verify tables exist
        $required_tables = ['medical_responses', 'notifications', 'problem_updates'];

        foreach ($required_tables as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() == 0) {
                error_log("Required table missing: $table. Please run complete database setup.");
                return false;
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Initialize tables error: " . $e->getMessage());
        return false;
    }
}

// Additional utility functions for complete dashboard functionality

function getRecentActivityActual($doctor_id, $days = 7) {
    try {
        $pdo = getDBConnection();

        $sql = "
            SELECT 'response' as activity_type, mr.created_at, p.title as description, p.priority
            FROM medical_responses mr
            INNER JOIN problems p ON mr.problem_id = p.id
            WHERE mr.doctor_id = ? AND mr.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)

            UNION ALL

            SELECT 'notification' as activity_type, n.created_at, n.title as description, n.priority
            FROM notifications n
            WHERE n.user_id = ? AND n.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)

            ORDER BY created_at DESC
            LIMIT 20
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$doctor_id, $days, $doctor_id, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Get recent activity error: " . $e->getMessage());
        return [];
    }
}

function updateLastLoginActual($user_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        return true;
    } catch (Exception $e) {
        error_log("Update last login error: " . $e->getMessage());
        return false;
    }
}

?>