-- ============================================================
-- Migration TV4: lịch sử trạng thái đơn hàng
-- Chạy một lần trên database ltweb_nhom12 (hoặc _test)
-- Compatible với MariaDB 11 / MySQL 8
-- ============================================================

-- Bảng lưu lịch sử đổi trạng thái đơn hàng
CREATE TABLE IF NOT EXISTS order_status_logs (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    order_id      INT UNSIGNED  NOT NULL,
    from_status   VARCHAR(20)   NOT NULL,
    to_status     VARCHAR(20)   NOT NULL,
    handled_by    INT UNSIGNED  NULL,          -- NULL = khách tự hủy
    handler_name  VARCHAR(100)  NOT NULL DEFAULT '',
    cancel_reason TEXT          NULL,           -- lý do hủy (nếu có)
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_log_order (order_id),
    INDEX idx_log_time  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index cho lọc đơn theo ngày (nếu chưa có)
CREATE INDEX IF NOT EXISTS idx_order_created ON orders(created_at);
