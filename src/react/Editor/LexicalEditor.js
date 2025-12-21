/**
 * LexicalEditor - Production implementation using Lexical
 *
 * Lexical is the official successor to Draft.js from the same team at Meta.
 *
 * @see https://lexical.dev/
 */

import React, { useEffect, useCallback, useRef, useState } from 'react';
import PropTypes from 'prop-types';

import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { RichTextPlugin } from '@lexical/react/LexicalRichTextPlugin';
import { ContentEditable } from '@lexical/react/LexicalContentEditable';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import { OnChangePlugin } from '@lexical/react/LexicalOnChangePlugin';
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { LexicalErrorBoundary } from '@lexical/react/LexicalErrorBoundary';
import { LinkPlugin } from '@lexical/react/LexicalLinkPlugin';
import { ListPlugin } from '@lexical/react/LexicalListPlugin';

import { HeadingNode, QuoteNode } from '@lexical/rich-text';
import { ListNode, ListItemNode } from '@lexical/list';
import { LinkNode, AutoLinkNode, $isLinkNode, TOGGLE_LINK_COMMAND } from '@lexical/link';
import {
	INSERT_ORDERED_LIST_COMMAND,
	INSERT_UNORDERED_LIST_COMMAND,
	REMOVE_LIST_COMMAND,
	$isListNode,
} from '@lexical/list';

import { $generateHtmlFromNodes, $generateNodesFromDOM } from '@lexical/html';
import {
	$getRoot,
	$insertNodes,
	$getSelection,
	$isRangeSelection,
	FORMAT_TEXT_COMMAND,
	SELECTION_CHANGE_COMMAND,
	COMMAND_PRIORITY_CRITICAL,
} from 'lexical';
import { $getNearestNodeOfType } from '@lexical/utils';

// Theme for styling the editor content
const theme = {
	paragraph: 'liveblog-lexical-paragraph',
	heading: {
		h1: 'liveblog-lexical-h1',
		h2: 'liveblog-lexical-h2',
		h3: 'liveblog-lexical-h3',
	},
	list: {
		ul: 'liveblog-lexical-ul',
		ol: 'liveblog-lexical-ol',
		listitem: 'liveblog-lexical-li',
		nested: {
			listitem: 'liveblog-lexical-li-nested',
		},
	},
	text: {
		bold: 'liveblog-lexical-bold',
		italic: 'liveblog-lexical-italic',
		underline: 'liveblog-lexical-underline',
		strikethrough: 'liveblog-lexical-strikethrough',
	},
	link: 'liveblog-lexical-link',
	quote: 'liveblog-lexical-quote',
};

// Nodes to register with the editor
const nodes = [
	HeadingNode,
	QuoteNode,
	ListNode,
	ListItemNode,
	LinkNode,
	AutoLinkNode,
];

/**
 * Plugin to load initial HTML content into the editor.
 * Only loads content once on mount to avoid cursor position issues.
 */
function InitialContentPlugin( { initialContent } ) {
	const [ editor ] = useLexicalComposerContext();
	const hasLoadedRef = useRef( false );

	useEffect( () => {
		// Only load initial content once
		if ( hasLoadedRef.current || ! initialContent ) {
			return;
		}

		hasLoadedRef.current = true;

		editor.update( () => {
			const parser = new DOMParser();
			const dom = parser.parseFromString( initialContent, 'text/html' );
			const generatedNodes = $generateNodesFromDOM( editor, dom );

			const root = $getRoot();
			root.clear();
			root.select();
			$insertNodes( generatedNodes );
		} );
	}, [ editor, initialContent ] );

	return null;
}

/**
 * Clean up Lexical's HTML output.
 * Removes unnecessary markup added by Lexical.
 *
 * @param {string} html - Raw HTML from Lexical.
 * @return {string} Cleaned HTML.
 */
function cleanLexicalHtml( html ) {
	let cleaned = html;

	// Remove style="white-space: pre-wrap;" from any element
	cleaned = cleaned.replace( / style="white-space: pre-wrap;"/g, '' );

	// Remove liveblog-lexical-* class attributes (not needed in stored HTML)
	cleaned = cleaned.replace( / class="liveblog-lexical-[^"]*"/g, '' );

	// Remove redundant <i> wrapping <em> (Lexical outputs both)
	cleaned = cleaned.replace( /<i>(<em[^>]*>)/g, '$1' );
	cleaned = cleaned.replace( /<\/em><\/i>/g, '</em>' );

	// Remove redundant <b> wrapping <strong>
	cleaned = cleaned.replace( /<b>(<strong[^>]*>)/g, '$1' );
	cleaned = cleaned.replace( /<\/strong><\/b>/g, '</strong>' );

	// Remove empty spans
	cleaned = cleaned.replace( /<span>([^<]*)<\/span>/g, '$1' );

	return cleaned;
}

/**
 * Plugin to export HTML when content changes.
 */
function HtmlExportPlugin( { onChange } ) {
	const [ editor ] = useLexicalComposerContext();

	const handleChange = useCallback(
		( editorState ) => {
			if ( ! onChange ) {
				return;
			}

			editorState.read( () => {
				const rawHtml = $generateHtmlFromNodes( editor, null );
				const html = cleanLexicalHtml( rawHtml );
				onChange( html );
			} );
		},
		[ editor, onChange ]
	);

	return <OnChangePlugin onChange={ handleChange } />;
}

/**
 * Toolbar button component using Dashicons.
 */
function ToolbarButton( { onClick, icon, title, isActive = false, disabled = false } ) {
	return (
		<button
			type="button"
			onClick={ onClick }
			title={ title }
			className={ `liveblog-editor-btn${ isActive ? ' is-active' : '' }` }
			disabled={ disabled }
			aria-label={ title }
		>
			<span className={ `dashicons dashicons-${ icon }` } />
		</button>
	);
}

ToolbarButton.propTypes = {
	onClick: PropTypes.func.isRequired,
	icon: PropTypes.string.isRequired,
	title: PropTypes.string.isRequired,
	isActive: PropTypes.bool,
	disabled: PropTypes.bool,
};

/**
 * Link input modal component.
 */
function LinkInput( { url, onChange, onConfirm, onCancel } ) {
	return (
		<div className="liveblog-editor-input-container">
			<input
				className="liveblog-input"
				type="url"
				value={ url }
				onChange={ ( e ) => onChange( e.target.value ) }
				placeholder="https://"
				// eslint-disable-next-line jsx-a11y/no-autofocus
				autoFocus
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' ) {
						e.preventDefault();
						onConfirm();
					} else if ( e.key === 'Escape' ) {
						onCancel();
					}
				} }
			/>
			<button
				type="button"
				className="liveblog-editor-btn liveblog-input-enter"
				onClick={ onConfirm }
				title="Add link"
			>
				<span className="dashicons dashicons-yes" />
			</button>
			<button
				type="button"
				className="liveblog-editor-btn liveblog-input-cancel"
				onClick={ onCancel }
				title="Cancel"
			>
				<span className="dashicons dashicons-no-alt" />
			</button>
		</div>
	);
}

LinkInput.propTypes = {
	url: PropTypes.string.isRequired,
	onChange: PropTypes.func.isRequired,
	onConfirm: PropTypes.func.isRequired,
	onCancel: PropTypes.func.isRequired,
};

/**
 * Get the current selection state for toolbar active states.
 */
function getSelectionState( editor ) {
	const state = {
		isBold: false,
		isItalic: false,
		isUnderline: false,
		isLink: false,
		listType: null,
	};

	editor.getEditorState().read( () => {
		const selection = $getSelection();
		if ( ! $isRangeSelection( selection ) ) {
			return;
		}

		state.isBold = selection.hasFormat( 'bold' );
		state.isItalic = selection.hasFormat( 'italic' );
		state.isUnderline = selection.hasFormat( 'underline' );

		const anchorNode = selection.anchor.getNode();
		const element = anchorNode.getKey() === 'root'
			? anchorNode
			: anchorNode.getTopLevelElementOrThrow();

		// Check for list
		if ( $isListNode( element ) ) {
			state.listType = element.getListType();
		} else {
			const parent = anchorNode.getParent();
			if ( parent ) {
				const listNode = $getNearestNodeOfType( parent, ListNode );
				if ( listNode ) {
					state.listType = listNode.getListType();
				}
			}
		}

		// Check for link
		const node = selection.anchor.getNode();
		const parent = node.getParent();
		state.isLink = $isLinkNode( parent ) || $isLinkNode( node );
	} );

	return state;
}

/**
 * Complete toolbar for text formatting.
 */
function ToolbarPlugin( { readOnly } ) {
	const [ editor ] = useLexicalComposerContext();
	const [ selectionState, setSelectionState ] = useState( {
		isBold: false,
		isItalic: false,
		isUnderline: false,
		isLink: false,
		listType: null,
	} );
	const [ showLinkInput, setShowLinkInput ] = useState( false );
	const [ linkUrl, setLinkUrl ] = useState( 'https://' );

	// Update selection state when selection changes
	useEffect( () => {
		return editor.registerCommand(
			SELECTION_CHANGE_COMMAND,
			() => {
				setSelectionState( getSelectionState( editor ) );
				return false;
			},
			COMMAND_PRIORITY_CRITICAL
		);
	}, [ editor ] );

	const formatBold = useCallback( () => {
		editor.dispatchCommand( FORMAT_TEXT_COMMAND, 'bold' );
	}, [ editor ] );

	const formatItalic = useCallback( () => {
		editor.dispatchCommand( FORMAT_TEXT_COMMAND, 'italic' );
	}, [ editor ] );

	const formatUnderline = useCallback( () => {
		editor.dispatchCommand( FORMAT_TEXT_COMMAND, 'underline' );
	}, [ editor ] );

	const formatOrderedList = useCallback( () => {
		if ( selectionState.listType === 'number' ) {
			editor.dispatchCommand( REMOVE_LIST_COMMAND, undefined );
		} else {
			editor.dispatchCommand( INSERT_ORDERED_LIST_COMMAND, undefined );
		}
	}, [ editor, selectionState.listType ] );

	const formatUnorderedList = useCallback( () => {
		if ( selectionState.listType === 'bullet' ) {
			editor.dispatchCommand( REMOVE_LIST_COMMAND, undefined );
		} else {
			editor.dispatchCommand( INSERT_UNORDERED_LIST_COMMAND, undefined );
		}
	}, [ editor, selectionState.listType ] );

	const openLinkModal = useCallback( () => {
		editor.getEditorState().read( () => {
			const selection = $getSelection();
			if ( ! $isRangeSelection( selection ) || selection.isCollapsed() ) {
				return;
			}

			// Check if there's an existing link
			const node = selection.anchor.getNode();
			const parent = node.getParent();
			if ( $isLinkNode( parent ) ) {
				setLinkUrl( parent.getURL() );
			} else if ( $isLinkNode( node ) ) {
				setLinkUrl( node.getURL() );
			} else {
				setLinkUrl( 'https://' );
			}

			setShowLinkInput( true );
		} );
	}, [ editor ] );

	const insertLink = useCallback( () => {
		if ( linkUrl && linkUrl !== 'https://' ) {
			editor.dispatchCommand( TOGGLE_LINK_COMMAND, linkUrl );
		}
		setShowLinkInput( false );
		setLinkUrl( 'https://' );
	}, [ editor, linkUrl ] );

	const removeLink = useCallback( () => {
		editor.dispatchCommand( TOGGLE_LINK_COMMAND, null );
		setShowLinkInput( false );
	}, [ editor ] );

	const cancelLink = useCallback( () => {
		setShowLinkInput( false );
		setLinkUrl( 'https://' );
	}, [] );

	return (
		<div className="liveblog-editor-toolbar-container">
			<div className="liveblog-toolbar">
				<ToolbarButton
					onClick={ formatBold }
					icon="editor-bold"
					title="Bold (Ctrl+B)"
					isActive={ selectionState.isBold }
					disabled={ readOnly }
				/>
				<ToolbarButton
					onClick={ formatItalic }
					icon="editor-italic"
					title="Italic (Ctrl+I)"
					isActive={ selectionState.isItalic }
					disabled={ readOnly }
				/>
				<ToolbarButton
					onClick={ formatUnderline }
					icon="editor-underline"
					title="Underline (Ctrl+U)"
					isActive={ selectionState.isUnderline }
					disabled={ readOnly }
				/>
				<ToolbarButton
					onClick={ formatOrderedList }
					icon="editor-ol"
					title="Ordered List"
					isActive={ selectionState.listType === 'number' }
					disabled={ readOnly }
				/>
				<ToolbarButton
					onClick={ formatUnorderedList }
					icon="editor-ul"
					title="Unordered List"
					isActive={ selectionState.listType === 'bullet' }
					disabled={ readOnly }
				/>
				<div style={ { position: 'relative', display: 'inline-block' } }>
					<ToolbarButton
						onClick={ openLinkModal }
						icon="admin-links"
						title="Add Link"
						isActive={ selectionState.isLink }
						disabled={ readOnly }
					/>
					{ showLinkInput && (
						<LinkInput
							url={ linkUrl }
							onChange={ setLinkUrl }
							onConfirm={ insertLink }
							onCancel={ cancelLink }
						/>
					) }
				</div>
				<ToolbarButton
					onClick={ removeLink }
					icon="editor-unlink"
					title="Remove Link"
					disabled={ readOnly || ! selectionState.isLink }
				/>
			</div>
		</div>
	);
}

ToolbarPlugin.propTypes = {
	readOnly: PropTypes.bool,
};

/**
 * Main Lexical Editor component.
 *
 * @param {Object}   props                - Component props.
 * @param {string}   props.initialContent - Initial HTML content to load.
 * @param {Function} props.onChange       - Callback when content changes, receives HTML string.
 * @param {boolean}  props.readOnly       - Whether the editor is read-only.
 */
const LexicalEditor = ( {
	initialContent = '',
	onChange = null,
	readOnly = false,
} ) => {
	const initialConfig = {
		namespace: 'LiveblogEditor',
		theme,
		nodes,
		editable: ! readOnly,
		onError: ( error ) => {
			// eslint-disable-next-line no-console
			console.error( 'Lexical error:', error );
		},
	};

	return (
		<div className="liveblog-lexical-editor">
			<LexicalComposer initialConfig={ initialConfig }>
				<ToolbarPlugin readOnly={ readOnly } />
				<div className="liveblog-lexical-editor-inner">
					<RichTextPlugin
						contentEditable={
							<ContentEditable className="liveblog-lexical-content-editable" />
						}
						placeholder={
							<div className="liveblog-lexical-placeholder">
								Start writing your liveblog entry...
							</div>
						}
						ErrorBoundary={ LexicalErrorBoundary }
					/>
				</div>
				<HistoryPlugin />
				<LinkPlugin />
				<ListPlugin />
				<InitialContentPlugin initialContent={ initialContent } />
				<HtmlExportPlugin onChange={ onChange } />
			</LexicalComposer>
		</div>
	);
};

LexicalEditor.propTypes = {
	initialContent: PropTypes.string,
	onChange: PropTypes.func,
	readOnly: PropTypes.bool,
};

export default LexicalEditor;
