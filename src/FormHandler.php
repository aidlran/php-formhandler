<?php

	namespace aidlo;

	use JetBrains\PhpStorm\ArrayShape;

	final class FormHandler {

		/** Data type constants for FormHandler::validate_form(). */
		const	TYPE_STRING	=  0,
				TYPE_INT	=  1,
				TYPE_FLOAT	=  2,
				TYPE_EMAIL	= 11;

		#[ArrayShape(['ok' => "false", 'err' => "string"])]
		private static function generateError(string $message): array {
			return [
				'ok' => false,
				'err' => $message
			];
		}

		#[ArrayShape(['ok' => "bool"])]
		private static function generateSuccess(): array {
			return ['ok' => true];
		}

		/**
		 * Validates forms that were delivered by POST using the provided array of field info.
		 *
		 * @param array[] $field_info An array of associative arrays containing information about the expected form
		 * fields.
		 *
		 * <br>
		 * <b>		Required:
		 * </b><ul>
		 * <li><b>		'name'	=> (string)
		 * </b><br>		The name used to access the data in $_POST[].
		 * </li>
		 * <li><b>		'label'	=> (string)
		 * </b><br>		The true name of the data to be used in error messages and likely shown to the user if the data
		 *				does not pass validation.
		 * </li>
		 * <li><b>		'type'	=> (int)
		 * </b><br>		The expected type of data using FormHandler::TYPE_* constants.
		 * </li></ul>
		 *
		 * <b>		Optional:
		 * </b><ul>
		 * <li><b>		'required'	=> (bool)
		 * </b><br>		Set this to true to enforce the field being required.
		 * </li>
		 * <li><b>		'max-size'	=> (int)
		 * </b><br>		Specify a max size or length for ints, floats and strings.
		 * </li>
		 * <li><b>		'min-size'	=> (int)
		 * </b><br>		Specify a min size or length for ints, floats and strings.
		 * </li></ul>
		 *
		 * @return array Returns an associative array:
		 * <ul>
		 * <li><b>		'ok'	=> (bool)
		 * </b><br>		TRUE if the validation passed without any issue, FALSE if not.
		 * </li>
		 * <li><b>		'err'	=> (string)
		 * </b><br>		If validation was not passed, this key will be included with a relevent error message that can
		 * 				be presented to the user.
		 */
		public static function validateForm(array $field_info): array
		{
			if ($_SERVER['REQUEST_METHOD'] != 'POST')
				return self::generateError('The request method was not POST.');
			else foreach ($field_info as $field) {
				// Figure out what to do with a blank field. If it is required, the form is not valid and an error is
				// generated. If it is provided but empty, unset it and proceed to the next field.
				if (($value = isset($_POST[$field['name']]) ? trim($_POST[$field['name']]) : '') == '')
					if (isset($field['required']) && $field['required'])
						return self::generateError($field['label'] . ' is required.');
					else {
						unset($_POST[$field['name']]);
						continue;
					}

				if (isset($field['type'])) {

					switch ($field['type']) {
						case self::TYPE_EMAIL:
							$value = filter_var($value, FILTER_SANITIZE_EMAIL);
							if (!filter_var($value, FILTER_VALIDATE_EMAIL))
								return self::generateError('Invalid email address provided.');
							$field['max-size'] = 254;
							break;
						case self::TYPE_INT:
							if (!preg_match('/^-?\d+$/', $value))
								return self::generateError($field['label'] . ' must be an integer.');
							break;
						case self::TYPE_FLOAT:
							if (!preg_match('/^-?\d+(\.\d+)?$/', $value))
								return self::generateError($field['label'] . ' must be a number.');
							break;
					}

					// Integer validation
					if ($field['type'] == self::TYPE_INT && !preg_match('/^-?\d+$/', $value))
						return self::generateError($field['label'] . ' must be an integer.');

					// Get size for checking against min/max-size
					if (($size = match ($field['type']) {
						self::TYPE_STRING, self::TYPE_EMAIL => strlen($value),
						self::TYPE_INT, self::TYPE_FLOAT => $value,
						default => null
					}) != null)
					{
						// Check against min-max-size
						if (isset($field['max-size']) && $field['max-size'] < $size)
							return self::generateError(match (($field['type'])) {
								self::TYPE_STRING	=> $field['label'] . ' too long (maximum ' . $field['max-size'] . ' characters.)',
								default				=> $field['label'] . ' too big (max: ' . $field['max-size'] . ')'
							});
						if (isset($field['min-size']) && $field['min-size'] > $size)
							return self::generateError(match (($field['type'])) {
								self::TYPE_STRING	=> $field['label'] . ' too short (minimum ' . $field['min-size'] . ' characters.)',
								default				=> $field['label'] . ' too small (min: ' . $field['min-size'] . ')'
							});
					}
				}
			}
			return self::generateSuccess();
		}

		private function __construct(){}
	}
