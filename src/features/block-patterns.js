import { createBlock, parse } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';

const getEditorPatternBlockConfig = (blockName) =>
	(window.vgtbtBlockPatterns?.blocks || {})[blockName];

const getSlugBasename = (slug) => {
	if (!slug || typeof slug !== 'string') {
		return '';
	}

	const parts = slug.split('/');
	return (parts[parts.length - 1] || '').trim();
};

const getPatternMarkup = (blockConfig, patternSlug) => {
	if (!blockConfig?.patterns) {
		return '';
	}

	if (patternSlug && blockConfig.patterns[patternSlug]) {
		return blockConfig.patterns[patternSlug];
	}

	const basename = getSlugBasename(patternSlug || blockConfig.defaultPattern);
	if (!basename) {
		return '';
	}

	if (blockConfig.patterns[basename]) {
		return blockConfig.patterns[basename];
	}

	const suffix = `/${basename}`;
	const matchingSlug = Object.keys(blockConfig.patterns).find((slug) =>
		slug.endsWith(suffix),
	);

	return matchingSlug ? blockConfig.patterns[matchingSlug] : '';
};

const getDefaultLock = (attributes, blockConfig) => {
	if (
		attributes?.lock &&
		typeof attributes.lock === 'object' &&
		Object.keys(attributes.lock).length
	) {
		return attributes.lock;
	}

	if (
		blockConfig?.defaultLock &&
		typeof blockConfig.defaultLock === 'object' &&
		Object.keys(blockConfig.defaultLock).length
	) {
		return blockConfig.defaultLock;
	}

	return {};
};

const applyDefaultLockToBlocks = (blocks, defaultLock) => {
	if (!Array.isArray(blocks) || !Object.keys(defaultLock || {}).length) {
		return blocks;
	}

	return blocks.map((block) => {
		const hasLock = !!(block.attributes && block.attributes.lock);
		const nextBlock = {
			...block,
			attributes: {
				...(block.attributes || {}),
			},
		};

		if (!hasLock) {
			nextBlock.attributes.lock = { ...defaultLock };
		}

		if (Array.isArray(block.innerBlocks) && block.innerBlocks.length) {
			nextBlock.innerBlocks = applyDefaultLockToBlocks(
				block.innerBlocks,
				defaultLock,
			);
		}

		return nextBlock;
	});
};

const resolvePatternSlug = (attributes, blockConfig) => {
	const raw = attributes?.blockPattern;
	if (raw !== undefined && raw !== null && String(raw).trim() !== '') {
		return String(raw).trim();
	}
	return blockConfig.defaultPattern
		? String(blockConfig.defaultPattern).trim()
		: '';
};

const resolveEffectiveTemplateLock = (attributes, blockConfig) => {
	const raw = attributes?.templateLock;
	if (raw !== undefined && raw !== null && String(raw).trim() !== '') {
		return String(raw).trim();
	}

	const defaultTemplateLock = blockConfig?.defaultTemplateLock
		? String(blockConfig.defaultTemplateLock).trim()
		: '';

	if (defaultTemplateLock) {
		return defaultTemplateLock;
	}

	if (blockConfig?.defaultContentRole) {
		return 'contentOnly';
	}

	return '';
};

const wrapContentOnlyGroup = (blocks) =>
	createBlock(
		'core/group',
		{
			className: 'acf-block-inner__container acf-block-content-only-wrapper',
			templateLock: 'contentOnly',
		},
		blocks,
	);

const withBlockPatternSeed = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { clientId, name, attributes } = props;
		const { replaceInnerBlocks } = useDispatch('core/block-editor');
		const innerBlocks = useSelect(
			(select) => select('core/block-editor').getBlocks(clientId),
			[clientId],
		);
		const innerCount = Array.isArray(innerBlocks) ? innerBlocks.length : 0;
		const lastAppliedPatternSlug = useRef(null);

		useEffect(() => {
			const blockConfig = getEditorPatternBlockConfig(name);

			if (!blockConfig || !clientId) {
				return;
			}

			const patternSlug = resolvePatternSlug(attributes, blockConfig);
			const markup = getPatternMarkup(blockConfig, patternSlug);
			if (!markup) {
				return;
			}

			const innerEmpty = innerCount === 0;
			const patternChanged = lastAppliedPatternSlug.current !== patternSlug;

			if (!patternChanged && !innerEmpty) {
				return;
			}

			const parsedBlocks = parse(markup);
			if (!Array.isArray(parsedBlocks) || !parsedBlocks.length) {
				return;
			}

			const defaultLock = getDefaultLock(attributes, blockConfig);
			const withLock = applyDefaultLockToBlocks(parsedBlocks, defaultLock);

			const templateLock = resolveEffectiveTemplateLock(
				attributes,
				blockConfig,
			);
			const wrapped =
				templateLock === 'contentOnly'
					? [wrapContentOnlyGroup(withLock)]
					: withLock;

			replaceInnerBlocks(clientId, wrapped, false);
			lastAppliedPatternSlug.current = patternSlug;
		}, [
			attributes?.blockPattern,
			attributes?.lock,
			attributes?.templateLock,
			attributes,
			name,
			clientId,
			innerCount,
			replaceInnerBlocks,
		]);

		return <BlockEdit {...props} />;
	};
}, 'withBlockPatternSeed');

addFilter(
	'editor.BlockEdit',
	'viget-blocks-toolkit/with-block-pattern-seed',
	withBlockPatternSeed,
);
