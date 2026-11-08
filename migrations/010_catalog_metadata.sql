-- @if-missing-column services sort_order
ALTER TABLE services ADD COLUMN sort_order INT NOT NULL DEFAULT 0;

-- @if-missing-column services icon_key
ALTER TABLE services ADD COLUMN icon_key VARCHAR(100) NULL;

-- @if-missing-column tariffs sort_order
ALTER TABLE tariffs ADD COLUMN sort_order INT NOT NULL DEFAULT 0;

-- @if-missing-column tariffs price_unit
ALTER TABLE tariffs ADD COLUMN price_unit VARCHAR(30) NOT NULL DEFAULT '₽';

-- @if-missing-column tariffs item_type
ALTER TABLE tariffs ADD COLUMN item_type VARCHAR(20) NOT NULL DEFAULT 'service';
