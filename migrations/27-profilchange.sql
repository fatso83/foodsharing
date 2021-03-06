CREATE TABLE `fs_foodsaver_change_history` (
  `date`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fs_id`       INT       NOT NULL,
  `changer_id`  INT       NOT NULL,
  `object_name` TEXT      NOT NULL,
  `old_value`   TEXT      NULL,
  `new_value`   TEXT      NULL,
  INDEX (`fs_id`)
)
  ENGINE = InnoDB;
