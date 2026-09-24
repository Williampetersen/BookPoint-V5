<?php
/**
 * Tiny WHERE-clause builder that keeps SQL fragments and parameters together.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Collects literal SQL fragments (with placeholders) and their values.
 */
final class Where {

	/**
	 * Fragments.
	 *
	 * @var string[]
	 */
	private $parts = array();

	/**
	 * Parameters.
	 *
	 * @var array
	 */
	private $params = array();

	/**
	 * Adds a condition. $fragment must be a literal string using %d/%s/%f/%i placeholders.
	 *
	 * @param string $fragment SQL fragment.
	 * @param mixed  ...$params Values.
	 * @return $this
	 */
	public function add( $fragment, ...$params ) {
		$this->parts[] = '(' . $fragment . ')';
		foreach ( $params as $param ) {
			$this->params[] = $param;
		}
		return $this;
	}

	/**
	 * Adds "column IN (...)" for integers.
	 *
	 * @param string $column Column reference (literal, e.g. "b.agent_id").
	 * @param int[]  $values Values.
	 * @return $this
	 */
	public function in_ints( $column, array $values ) {
		$values = array_values( array_map( 'intval', $values ) );
		if ( ! $values ) {
			$this->parts[] = '(1 = 0)';
			return $this;
		}
		$this->parts[] = '(' . $column . ' IN (' . implode( ',', array_fill( 0, count( $values ), '%d' ) ) . '))';
		foreach ( $values as $value ) {
			$this->params[] = $value;
		}
		return $this;
	}

	/**
	 * Adds "column IN (...)" for strings.
	 *
	 * @param string   $column Column reference.
	 * @param string[] $values Values.
	 * @return $this
	 */
	public function in_strings( $column, array $values ) {
		$values = array_values( array_map( 'strval', $values ) );
		if ( ! $values ) {
			$this->parts[] = '(1 = 0)';
			return $this;
		}
		$this->parts[] = '(' . $column . ' IN (' . implode( ',', array_fill( 0, count( $values ), '%s' ) ) . '))';
		foreach ( $values as $value ) {
			$this->params[] = $value;
		}
		return $this;
	}

	/**
	 * SQL for the WHERE clause (empty string when there are no conditions).
	 *
	 * @return string
	 */
	public function sql() {
		return $this->parts ? ' WHERE ' . implode( ' AND ', $this->parts ) : '';
	}

	/**
	 * Parameters in order.
	 *
	 * @return array
	 */
	public function params() {
		return $this->params;
	}
}
