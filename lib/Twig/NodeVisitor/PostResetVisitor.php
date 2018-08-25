<?php

namespace Timber\Twig\NodeVisitor;

use Twig_BaseNodeVisitor;
use Twig_Environment;
use Twig_Node;
use Twig_Node_For;

class PostResetVisitor extends Twig_BaseNodeVisitor {
	public function doEnterNode( Twig_Node $node, Twig_Environment $env ) {
		return $node;
	}

	public function doLeaveNode( Twig_Node $node, Twig_Environment $env ) {
		if ( ! $node instanceof Twig_Node_For ) {
			return $node;
		}

		if (function_exists('wp_reset_postdata')) {
			wp_reset_postdata();
	    }

		return $node;
	}

	public function getPriority() {
		return 0;
	}
}
