<?php

	/**
	 * The object browser used to be a frame of its own. It is part of every
	 * page now, so send old links and bookmarks to the start page.
	 */
	header('Location: ./', true, 301);
