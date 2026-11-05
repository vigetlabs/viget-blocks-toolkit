/**
 * Post Field Groups
 *
 * Edits a block's post-level ACF field groups in the block. Edit mode moves the
 * fields out of their meta box into a panel laid over the block, so ACF and the
 * meta box save keep working. Preview renders the block with the unsaved values.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { BlockControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	Notice,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	createPortal,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';

const CONTEXT_KEY = 'vgtbtPostFields';
const FIELD_SELECTOR = 'input, select, textarea';
const blockGroups = window.vgtbtPostFieldGroups || {};
const modes = new Map();

/**
 * Wrap a field group's meta box contents so they can move as one node.
 *
 * @param {string} key Field group key.
 * @return {?HTMLElement} The wrapper.
 */
function wrapFields( key ) {
	const postbox = document.getElementById( `acf-${ key }` );
	const inside = postbox?.querySelector( '.inside' );

	if ( ! inside ) {
		return null;
	}

	let wrapper = inside.querySelector( ':scope > .vgtbt-post-fields' );

	if ( ! wrapper ) {
		wrapper = document.createElement( 'div' );
		wrapper.className = 'vgtbt-post-fields';
		wrapper.append( ...inside.childNodes );
		inside.append( wrapper );
	}

	if ( ! inside.querySelector( ':scope > .vgtbt-post-fields-notice' ) ) {
		const notice = document.createElement( 'div' );
		notice.className = 'vgtbt-post-fields-notice';
		inside.prepend( notice );
	}

	wrapper.vgtbtInside = inside;

	return wrapper;
}

/**
 * Put a wrapper's fields back in their meta box.
 *
 * @param {HTMLElement} wrapper Wrapper from wrapFields().
 */
function unwrapFields( wrapper ) {
	const { vgtbtInside: inside } = wrapper;

	returnFields( wrapper );
	inside.querySelector( ':scope > .vgtbt-post-fields-notice' )?.remove();
	inside.append( ...wrapper.childNodes );
	wrapper.remove();
	wrapper.vgtbtRemoved = true;
}

/**
 * Move a wrapper back into its meta box form.
 *
 * @param {HTMLElement} wrapper Wrapper from wrapFields().
 */
function returnFields( wrapper ) {
	if ( wrapper.vgtbtRemoved || wrapper.parentNode === wrapper.vgtbtInside ) {
		return;
	}

	window.acf?.doAction( 'unmount', window.jQuery( wrapper ) );
	wrapper.querySelectorAll( '[data-vgtbt-form]' ).forEach( ( field ) => {
		field.removeAttribute( 'form' );
		field.removeAttribute( 'data-vgtbt-form' );
	} );
	wrapper.vgtbtInside.append( wrapper );
	window.acf?.doAction( 'remount', window.jQuery( wrapper ) );
}

/**
 * Keep fields outside the meta box form submitting with it.
 *
 * @param {HTMLElement} root   Element holding the fields.
 * @param {string}      formId Meta box form id.
 */
function associateFields( root, formId ) {
	root.querySelectorAll( FIELD_SELECTOR ).forEach( ( field ) => {
		if ( ! field.hasAttribute( 'form' ) ) {
			field.setAttribute( 'form', formId );
			field.setAttribute( 'data-vgtbt-form', '' );
		}
	} );
}

/**
 * Get the id of the meta box form holding a wrapper, adding one if needed.
 *
 * @param {HTMLElement} wrapper Wrapper from wrapFields().
 * @return {string} Form id.
 */
function getFormId( wrapper ) {
	const form = wrapper.vgtbtInside.closest( 'form' );

	if ( ! form.id ) {
		const location = form.className.match( /metabox-location-[\w-]+/ );
		form.id = `vgtbt-${ location ? location[ 0 ] : 'metabox-form' }`;
	}

	return form.id;
}

/**
 * Whether a block is inside a synced pattern, where it can't be edited.
 *
 * @param {(store: string) => Object} select   Registry select.
 * @param {string}                    clientId Block client id.
 * @return {boolean} Whether it's in a synced pattern.
 */
function isInSyncedPattern( select, clientId ) {
	return (
		select( 'core/block-editor' ).getBlockParentsByBlockName(
			clientId,
			'core/block'
		).length > 0
	);
}

/**
 * Whether this is the first block on the post using any of these field groups, outside synced patterns.
 *
 * @param {string}   clientId Block client id.
 * @param {string[]} groups   Field group keys.
 * @return {boolean} Whether the block owns the fields.
 */
function useIsOwner( clientId, groups ) {
	return useSelect(
		( select ) => {
			const { getClientIdsWithDescendants, getBlockName } =
				select( 'core/block-editor' );
			const owner = getClientIdsWithDescendants().find(
				( id ) =>
					( blockGroups[ getBlockName( id ) ] || [] ).some( ( key ) =>
						groups.includes( key )
					) && ! isInSyncedPattern( select, id )
			);

			return owner === clientId;
		},
		[ clientId, groups ]
	);
}

/**
 * Take over the field groups' meta boxes and track their unsaved values.
 *
 * @param {boolean}  isOwner Whether this block owns the fields.
 * @param {string[]} groups  Field group keys.
 * @return {{values: string, notices: HTMLElement[]}} Serialized field values, and the meta box notice containers.
 */
function useFieldValues( isOwner, groups ) {
	const [ values, setValues ] = useState( '' );
	const [ notices, setNotices ] = useState( [] );

	useEffect( () => {
		const { acf, jQuery } = window;
		const wrappers = isOwner
			? groups.map( wrapFields ).filter( Boolean )
			: [];

		if ( ! wrappers.length || ! acf ) {
			return;
		}

		const $wrappers = jQuery( wrappers );
		const update = () =>
			setValues( JSON.stringify( acf.serialize( $wrappers, 'acf' ) ) );
		let timer;
		const queue = () => {
			clearTimeout( timer );
			timer = setTimeout( update, 300 );
		};

		update();
		setNotices(
			wrappers.map( ( wrapper ) =>
				wrapper.vgtbtInside.querySelector(
					':scope > .vgtbt-post-fields-notice'
				)
			)
		);
		$wrappers.on( 'change keyup', queue );
		acf.addAction( 'append', queue );
		acf.addAction( 'remove', queue );

		return () => {
			clearTimeout( timer );
			$wrappers.off( 'change keyup', queue );
			acf.removeAction( 'append', queue );
			acf.removeAction( 'remove', queue );
			setNotices( [] );
			wrappers.forEach( unwrapFields );
		};
	}, [ isOwner, groups ] );

	return { values, notices };
}

/**
 * Edit mode: a placeholder in the canvas with the fields laid over it.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.clientId Block client id.
 * @param {string[]} props.groups   Field group keys.
 * @return {Element} Placeholder and panel.
 */
function FieldsPanel( { clientId, groups } ) {
	const { selectBlock } = useDispatch( 'core/block-editor' );
	const placeholderRef = useRef();
	const panelRef = useRef();
	const blockProps = useBlockProps( {
		ref: placeholderRef,
		className: 'vgtbt-post-fields-placeholder',
	} );

	useEffect( () => {
		const placeholder = placeholderRef.current;
		const panel = panelRef.current;
		const frame = placeholder.ownerDocument.defaultView.frameElement;
		const wrappers = groups.map( wrapFields ).filter( Boolean );
		const observers = wrappers.map( ( wrapper ) => {
			const formId = getFormId( wrapper );
			const observer = new window.MutationObserver( () =>
				associateFields( wrapper, formId )
			);

			window.acf?.doAction( 'unmount', window.jQuery( wrapper ) );
			panel.append( wrapper );
			associateFields( wrapper, formId );
			observer.observe( wrapper, { childList: true, subtree: true } );
			window.acf?.doAction( 'remount', window.jQuery( wrapper ) );

			return observer;
		} );

		// Follow the placeholder, clipped to the canvas, and size it to the panel.
		let frameId;
		const follow = () => {
			const canvas = frame
				? frame.getBoundingClientRect()
				: { top: 0, left: 0, bottom: window.innerHeight };
			const rect = placeholder.getBoundingClientRect();
			const top = canvas.top + rect.top;
			const height = panel.offsetHeight;

			panel.style.transform = `translate(${ canvas.left + rect.left }px, ${ top }px)`;
			panel.style.width = `${ rect.width }px`;
			panel.style.clipPath = `inset(${ Math.max( 0, canvas.top - top ) }px 0 ${ Math.max( 0, top + height - canvas.bottom ) }px 0)`;
			placeholder.style.height = `${ height }px`;
			frameId = window.requestAnimationFrame( follow );
		};

		// The panel sits outside the canvas, so pass its scrolling through.
		const scroll = ( event ) =>
			frame?.contentWindow.scrollBy( event.deltaX, event.deltaY );

		// The panel sits outside the block too, so select the block when it's used, which brings back its toolbar.
		const select = () => selectBlock( clientId, null );

		follow();
		panel.addEventListener( 'wheel', scroll, { passive: true } );
		panel.addEventListener( 'focusin', select );
		panel.addEventListener( 'mousedown', select );

		return () => {
			window.cancelAnimationFrame( frameId );
			panel.removeEventListener( 'wheel', scroll );
			panel.removeEventListener( 'focusin', select );
			panel.removeEventListener( 'mousedown', select );
			observers.forEach( ( observer ) => observer.disconnect() );
			wrappers.forEach( returnFields );
		};
	}, [ clientId, groups ] ); // eslint-disable-line react-hooks/exhaustive-deps

	return (
		<>
			<div { ...blockProps } />
			{ createPortal(
				<div ref={ panelRef } className="vgtbt-post-fields-panel" />,
				// Inside ACF's validation root, under the editor's popovers like the block toolbar.
				document.querySelector(
					'#wpbody-content > .block-editor .editor-editor-interface'
				) ||
					document.querySelector(
						'#wpbody-content > .block-editor'
					) ||
					document.body
			) }
		</>
	);
}

/**
 * Tells people looking in the meta box which block has its fields open.
 *
 * @param {Object}     props          Props.
 * @param {string}     props.title    Block title.
 * @param {() => void} props.onSelect Selects the block.
 * @param {() => void} props.onReturn Moves the fields back to the meta box.
 * @return {Element} Notice.
 */
function MetaBoxNotice( { title, onSelect, onReturn } ) {
	return (
		<Notice status="info" isDismissible={ false }>
			<p>
				{ sprintf(
					/* translators: %s: Block title. */
					__(
						'These fields are open in the %s block.',
						'viget-blocks-toolkit'
					),
					title
				) }
			</p>
			<Button variant="secondary" onClick={ onSelect }>
				{ __( 'Go to block', 'viget-blocks-toolkit' ) }
			</Button>{ ' ' }
			<Button variant="tertiary" onClick={ onReturn }>
				{ __( 'Edit here', 'viget-blocks-toolkit' ) }
			</Button>
		</Notice>
	);
}

/**
 * Edit mode for a block that doesn't own the fields.
 *
 * @return {Element} Notice.
 */
function NotOwnerNotice() {
	return (
		<div { ...useBlockProps() }>
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'These fields are edited in the first block on this page that uses them.',
					'viget-blocks-toolkit'
				) }
			</Notice>
		</div>
	);
}

/**
 * Edit/Preview toggle around the block.
 *
 * @param {Object}                     props           Props.
 * @param {(props: Object) => Element} props.BlockEdit Original block edit component.
 * @param {string[]}                   props.groups    Field group keys.
 * @param {Object}                     props.props     Block edit props.
 * @return {Element} Block edit.
 */
function PostFieldGroupsEdit( { BlockEdit, groups, props } ) {
	const { attributes, clientId, context } = props;
	const isOwner = useIsOwner( clientId, groups );
	const { values, notices } = useFieldValues( isOwner, groups );
	const title = useSelect(
		( select ) => select( 'core/blocks' ).getBlockType( props.name )?.title,
		[ props.name ]
	);

	// ACF refetches the preview when the attributes object changes, and sends the context with it.
	const previewAttributes = useMemo(
		() => ( { ...attributes } ),
		[ attributes, values ] // eslint-disable-line react-hooks/exhaustive-deps
	);
	const [ mode, setMode ] = useState(
		() => modes.get( clientId ) || 'preview'
	);
	const isEditing = 'edit' === mode;
	const { selectBlock } = useDispatch( 'core/block-editor' );

	const changeMode = ( next ) => {
		modes.set( clientId, next );
		setMode( next );
	};

	const toggle = () => changeMode( isEditing ? 'preview' : 'edit' );

	let edit = (
		<BlockEdit
			{ ...props }
			attributes={ previewAttributes }
			context={ { ...context, [ CONTEXT_KEY ]: values } }
		/>
	);

	if ( isEditing ) {
		edit = isOwner ? (
			<FieldsPanel clientId={ clientId } groups={ groups } />
		) : (
			<NotOwnerNotice />
		);
	}

	return (
		<>
			<BlockControls group="other">
				<ToolbarGroup>
					<ToolbarButton
						icon={ isEditing ? 'visibility' : 'edit' }
						label={
							isEditing
								? __( 'Preview', 'viget-blocks-toolkit' )
								: __( 'Edit fields', 'viget-blocks-toolkit' )
						}
						onClick={ toggle }
					/>
				</ToolbarGroup>
			</BlockControls>
			{ edit }
			{ isEditing &&
				notices.map( ( notice ) =>
					createPortal(
						<MetaBoxNotice
							title={ title }
							onSelect={ () => selectBlock( clientId ) }
							onReturn={ () => changeMode( 'preview' ) }
						/>,
						notice
					)
				) }
		</>
	);
}

addFilter(
	'blocks.registerBlockType',
	'viget-blocks-toolkit/post-field-groups-single',
	( settings, name ) =>
		blockGroups[ name ]
			? {
					...settings,
					supports: { ...settings.supports, multiple: false },
				}
			: settings
);

/**
 * A block in a synced pattern, or a copy core disabled for being used more than once,
 * renders as usual and leaves the fields in their meta box.
 *
 * @param {Object}                     props           Props.
 * @param {(props: Object) => Element} props.BlockEdit Original block edit component.
 * @param {string[]}                   props.groups    Field group keys.
 * @param {Object}                     props.props     Block edit props.
 * @return {Element} Block edit.
 */
function PostFieldGroupsBlock( { BlockEdit, groups, props } ) {
	const isPlain = useSelect(
		( select ) =>
			isInSyncedPattern( select, props.clientId ) ||
			select( 'core/block-editor' ).getBlocksByName( props.name )[ 0 ] !==
				props.clientId,
		[ props.clientId, props.name ]
	);

	if ( isPlain ) {
		return <BlockEdit { ...props } />;
	}

	return (
		<PostFieldGroupsEdit
			BlockEdit={ BlockEdit }
			groups={ groups }
			props={ props }
		/>
	);
}

addFilter(
	'editor.BlockEdit',
	'viget-blocks-toolkit/post-field-groups',
	createHigherOrderComponent(
		( BlockEdit ) => ( props ) =>
			blockGroups[ props.name ]?.length ? (
				<PostFieldGroupsBlock
					BlockEdit={ BlockEdit }
					groups={ blockGroups[ props.name ] }
					props={ props }
				/>
			) : (
				<BlockEdit { ...props } />
			),
		'withPostFieldGroups'
	)
);
