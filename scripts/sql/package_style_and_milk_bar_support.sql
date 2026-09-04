-- Highland Fresh: explicit packaging components and Milk Bar product category.
-- Safe to run once on an existing installation. Runtime endpoints also apply
-- these additive changes for installations deployed without CLI access.

ALTER TABLE `ingredients`
  ADD COLUMN IF NOT EXISTS `packaging_form` VARCHAR(30) NULL AFTER `packaging_role`;

ALTER TABLE `base_products`
  MODIFY `category` ENUM(
    'pasteurized_milk','flavored_milk','yogurt','cheese','butter','cream','milk_bar'
  ) NOT NULL;

ALTER TABLE `products`
  MODIFY `category` ENUM(
    'pasteurized_milk','flavored_milk','yogurt','cheese','butter','cream','milk_bar'
  ) NOT NULL;
