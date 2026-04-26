<?php
/**
 * Template renderer interface.
 *
 * @package Automattic\Liveblog\Application\Renderer
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Renderer;

/**
 * Renders a template with a set of variables.
 *
 * Application code depends on this interface so it never reaches into the
 * infrastructure layer for template rendering.
 */
interface TemplateRendererInterface {

	/**
	 * Render a template.
	 *
	 * @param string               $template_name Template file name (relative to the templates directory).
	 * @param array<string, mixed> $variables     Variables to expose to the template.
	 * @return string The rendered output.
	 */
	public function render( string $template_name, array $variables = array() ): string;
}
