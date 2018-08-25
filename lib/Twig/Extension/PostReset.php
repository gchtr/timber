<?php

namespace Timber\Twig\Extension;

use Timber\Twig\NodeVisitor\PostResetVisitor;

class PostReset extends \Twig_Extension {
	public function getNodeVisitors() {
        return array(new PostResetVisitor());
    }
}
