/**
 * LexicalEditor - Production implementation using Lexical
 *
 * Lexical is the official successor to Draft.js from the same team at Meta.
 *
 * @see https://lexical.dev/
 */

import React, { useEffect, useCallback, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { __ } from '@wordpress/i18n';

import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { RichTextPlugin } from '@lexical/react/LexicalRichTextPlugin';
import { ContentEditable } from '@lexical/react/LexicalContentEditable';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import { OnChangePlugin } from '@lexical/react/LexicalOnChangePlugin';
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { LexicalErrorBoundary } from '@lexical/react/LexicalErrorBoundary';
import { LinkPlugin } from '@lexical/react/LexicalLinkPlugin';
import { ListPlugin } from '@lexical/react/LexicalListPlugin';

import { HeadingNode, QuoteNode, $isQuoteNode, $createQuoteNode } from '@lexical/rich-text';
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
	$setSelection,
	$isRangeSelection,
	$isNodeSelection,
	$getNodeByKey,
	$createNodeSelection,
	$createParagraphNode,
	FORMAT_TEXT_COMMAND,
	SELECTION_CHANGE_COMMAND,
	COMMAND_PRIORITY_CRITICAL,
	COMMAND_PRIORITY_HIGH,
	COMMAND_PRIORITY_EDITOR,
	createCommand,
	DecoratorNode,
	DRAGOVER_COMMAND,
	DROP_COMMAND,
	KEY_ENTER_COMMAND,
	KEY_ARROW_DOWN_COMMAND,
	KEY_ARROW_UP_COMMAND,
	KEY_ESCAPE_COMMAND,
	KEY_TAB_COMMAND,
} from 'lexical';
import { $getNearestNodeOfType } from '@lexical/utils';
import { $setBlocksType } from '@lexical/selection';

import ImageCropModal from './ImageCropModal';

// ============================================================================
// Image Node
// ============================================================================

const INSERT_IMAGE_COMMAND = createCommand( 'INSERT_IMAGE_COMMAND' );

/**
 * ResizableImage - Component for displaying images with resize handles.
 *
 * @param {Object} props           Component props.
 * @param {string} props.src       Image source URL.
 * @param {string} props.alt       Image alt text.
 * @param {number} props.width     Image width (0 = auto).
 * @param {number} props.height    Image height (0 = auto).
 * @param {string} props.nodeKey   Lexical node key for updates.
 */
function ResizableImage( { src, alt, width, height, nodeKey } ) {
	const [ editor ] = useLexicalComposerContext();
	const imageRef = useRef( null );
	const [ isResizing, setIsResizing ] = useState( false );
	const [ isSelected, setIsSelected ] = useState( false );
	const startSize = useRef( { width: 0, height: 0 } );
	const startPos = useRef( { x: 0, y: 0 } );
	const aspectRatio = useRef( 1 );

	// Track selection state.
	useEffect( () => {
		return editor.registerCommand(
			SELECTION_CHANGE_COMMAND,
			() => {
				editor.getEditorState().read( () => {
					const selection = $getSelection();
					if ( $isNodeSelection( selection ) ) {
						const nodes = selection.getNodes();
						const selected = nodes.some(
							( node ) => $isImageNode( node ) && node.getKey() === nodeKey
						);
						setIsSelected( selected );
					} else {
						setIsSelected( false );
					}
				} );
				return false;
			},
			COMMAND_PRIORITY_HIGH
		);
	}, [ editor, nodeKey ] );

	// Handle click to select image.
	const handleClick = useCallback(
		( event ) => {
			event.preventDefault();
			editor.update( () => {
				const node = $getNodeByKey( nodeKey );
				if ( node ) {
					const selection = $createNodeSelection();
					selection.add( nodeKey );
					$setSelection( selection );
				}
			} );
		},
		[ editor, nodeKey ]
	);

	// Start resize on mousedown.
	const handleResizeStart = useCallback(
		( event ) => {
			event.preventDefault();
			event.stopPropagation();

			const img = imageRef.current;
			if ( ! img ) {
				return;
			}

			setIsResizing( true );
			startSize.current = {
				width: img.offsetWidth,
				height: img.offsetHeight,
			};
			startPos.current = { x: event.clientX, y: event.clientY };
			aspectRatio.current = img.offsetWidth / img.offsetHeight;
		},
		[]
	);

	// Handle resize drag.
	useEffect( () => {
		if ( ! isResizing ) {
			return;
		}

		const handleMouseMove = ( event ) => {
			const deltaX = event.clientX - startPos.current.x;
			const newWidth = Math.max( 50, startSize.current.width + deltaX );
			const newHeight = Math.round( newWidth / aspectRatio.current );

			editor.update( () => {
				const node = $getNodeByKey( nodeKey );
				if ( node && $isImageNode( node ) ) {
					node.setDimensions( newWidth, newHeight );
				}
			} );
		};

		const handleMouseUp = () => {
			setIsResizing( false );
		};

		document.addEventListener( 'mousemove', handleMouseMove );
		document.addEventListener( 'mouseup', handleMouseUp );

		return () => {
			document.removeEventListener( 'mousemove', handleMouseMove );
			document.removeEventListener( 'mouseup', handleMouseUp );
		};
	}, [ isResizing, editor, nodeKey ] );

	const style = {};
	if ( width > 0 ) {
		style.width = `${ width }px`;
	}
	if ( height > 0 ) {
		style.height = `${ height }px`;
	}

	return (
		<span
			className={ `liveblog-resizable-image-container${
				isSelected ? ' selected' : ''
			}${ isResizing ? ' resizing' : '' }` }
		>
			<img
				ref={ imageRef }
				src={ src }
				alt={ alt }
				className="liveblog-lexical-image"
				style={ style }
				onClick={ handleClick }
				draggable={ false }
			/>
			{ isSelected && (
				<>
					<span
						className="liveblog-resize-handle liveblog-resize-handle-se"
						onMouseDown={ handleResizeStart }
					/>
				</>
			) }
		</span>
	);
}

/**
 * ImageNode - Custom Lexical node for displaying images.
 * Preserves all attributes from the original <img> element for flexible rendering.
 */
class ImageNode extends DecoratorNode {
	__src;
	__alt;
	__attributes;

	static getType() {
		return 'image';
	}

	static clone( node ) {
		return new ImageNode( node.__src, node.__alt, node.__attributes, node.__key );
	}

	constructor( src, alt = '', attributes = {}, key ) {
		super( key );
		this.__src = src;
		this.__alt = alt;
		// Store all attributes, ensuring src and alt are always present
		this.__attributes = { ...attributes, src, alt };
	}

	setDimensions( width, height ) {
		const writable = this.getWritable();
		writable.__attributes = {
			...writable.__attributes,
			width: String( width ),
			height: String( height ),
		};
	}

	getWidth() {
		return parseInt( this.__attributes.width, 10 ) || 0;
	}

	getHeight() {
		return parseInt( this.__attributes.height, 10 ) || 0;
	}

	createDOM() {
		const span = document.createElement( 'span' );
		span.className = 'liveblog-lexical-image-wrapper';
		return span;
	}

	updateDOM() {
		return false;
	}

	static importJSON( serializedNode ) {
		const { src, alt, attributes = {} } = serializedNode;
		return $createImageNode( src, alt, attributes );
	}

	exportJSON() {
		return {
			type: 'image',
			version: 1,
			src: this.__src,
			alt: this.__alt,
			attributes: this.__attributes,
		};
	}

	static importDOM() {
		return {
			img: () => ( {
				conversion: convertImageElement,
				priority: 0,
			} ),
		};
	}

	exportDOM() {
		const img = document.createElement( 'img' );
		// Export all stored attributes
		Object.entries( this.__attributes ).forEach( ( [ key, value ] ) => {
			if ( value !== null && value !== undefined && value !== '' ) {
				img.setAttribute( key, value );
			}
		} );
		return { element: img };
	}

	decorate() {
		return (
			<ResizableImage
				src={ this.__src }
				alt={ this.__alt }
				width={ this.getWidth() }
				height={ this.getHeight() }
				nodeKey={ this.getKey() }
			/>
		);
	}
}

function convertImageElement( domNode ) {
	if ( domNode instanceof HTMLImageElement ) {
		const src = domNode.getAttribute( 'src' );
		if ( src ) {
			// Collect all attributes from the original element
			const attributes = {};
			for ( const attr of domNode.attributes ) {
				attributes[ attr.name ] = attr.value;
			}
			const alt = attributes.alt || '';
			return { node: $createImageNode( src, alt, attributes ) };
		}
	}
	return null;
}

function $createImageNode( src, alt = '', attributes = {} ) {
	return new ImageNode( src, alt, attributes );
}

function $isImageNode( node ) {
	return node instanceof ImageNode;
}

// ============================================================================
// Theme
// ============================================================================

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
	image: 'liveblog-lexical-image',
};

// Nodes to register with the editor
const nodes = [
	HeadingNode,
	QuoteNode,
	ListNode,
	ListItemNode,
	LinkNode,
	AutoLinkNode,
	ImageNode,
];

// ============================================================================
// Plugins
// ============================================================================

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

	// Clean up image wrapper spans
	cleaned = cleaned.replace( /<span class="liveblog-lexical-image-wrapper">(<img[^>]*>)<\/span>/g, '$1' );

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
 * Plugin to handle image insertion and drag-drop.
 */
function ImagePlugin( { handleImageUpload } ) {
	const [ editor ] = useLexicalComposerContext();

	useEffect( () => {
		// Handle INSERT_IMAGE_COMMAND
		const unregisterInsertImage = editor.registerCommand(
			INSERT_IMAGE_COMMAND,
			( payload ) => {
				const { src, alt } = payload;
				const imageNode = $createImageNode( src, alt );

				const selection = $getSelection();
				if ( $isRangeSelection( selection ) ) {
					selection.insertNodes( [ imageNode ] );
				} else {
					const root = $getRoot();
					const paragraph = $createParagraphNode();
					paragraph.append( imageNode );
					root.append( paragraph );
				}

				return true;
			},
			COMMAND_PRIORITY_EDITOR
		);

		// Handle drag over
		const unregisterDragOver = editor.registerCommand(
			DRAGOVER_COMMAND,
			( event ) => {
				const hasFiles = event.dataTransfer?.types?.includes( 'Files' );
				if ( hasFiles ) {
					event.preventDefault();
					return true;
				}
				return false;
			},
			COMMAND_PRIORITY_HIGH
		);

		// Handle drop
		const unregisterDrop = editor.registerCommand(
			DROP_COMMAND,
			( event ) => {
				const files = event.dataTransfer?.files;
				if ( files && files.length > 0 ) {
					// Filter to only image files
					const imageFiles = Array.from( files ).filter( ( file ) =>
						file.type.startsWith( 'image/' )
					);

					if ( imageFiles.length > 0 ) {
						event.preventDefault();

						if ( handleImageUpload ) {
							// Upload all images sequentially to maintain order
							imageFiles.reduce( ( promise, file ) => {
								return promise.then( () =>
									handleImageUpload( file ).then( ( src ) => {
										editor.dispatchCommand( INSERT_IMAGE_COMMAND, {
											src,
											alt: file.name,
										} );
									} ).catch( ( err ) => {
										// eslint-disable-next-line no-console
										console.error( 'Image upload failed:', err );
									} )
								);
							}, Promise.resolve() );
						}

						return true;
					}
				}
				return false;
			},
			COMMAND_PRIORITY_HIGH
		);

		return () => {
			unregisterInsertImage();
			unregisterDragOver();
			unregisterDrop();
		};
	}, [ editor, handleImageUpload ] );

	return null;
}

// ============================================================================
// Autocomplete Plugin
// ============================================================================

const AUTOCOMPLETE_TRIGGERS = [ '@', '#', '/', ':' ];

/**
 * Get the current trigger and search text from the selection.
 *
 * @param {Object} editor - Lexical editor instance.
 * @return {Object|null} Object with trigger and text, or null if no trigger found.
 */
function getTriggerMatch( editor ) {
	let result = null;

	editor.getEditorState().read( () => {
		const selection = $getSelection();
		if ( ! $isRangeSelection( selection ) || ! selection.isCollapsed() ) {
			return;
		}

		const anchor = selection.anchor;
		const anchorNode = anchor.getNode();

		// Only handle text nodes
		if ( anchorNode.getType() !== 'text' ) {
			return;
		}

		const textContent = anchorNode.getTextContent();
		const offset = anchor.offset;

		// Look backwards from cursor to find a trigger
		for ( let i = offset - 1; i >= 0; i-- ) {
			const char = textContent[ i ];

			// Found a space before finding a trigger - no match
			if ( char === ' ' || char === '\n' ) {
				return;
			}

			// Found a trigger
			if ( AUTOCOMPLETE_TRIGGERS.includes( char ) ) {
				// Check if trigger is at start of text or preceded by space
				if ( i === 0 || textContent[ i - 1 ] === ' ' || textContent[ i - 1 ] === '\n' ) {
					result = {
						trigger: char,
						text: textContent.slice( i + 1, offset ),
						startOffset: i,
						endOffset: offset,
					};
				}
				return;
			}
		}
	} );

	return result;
}

/**
 * Plugin to handle autocomplete suggestions.
 */
function AutocompletePlugin( { suggestions, onSearch } ) {
	const [ editor ] = useLexicalComposerContext();
	const [ triggerMatch, setTriggerMatch ] = useState( null );
	const [ selectedIndex, setSelectedIndex ] = useState( 0 );
	const [ menuPosition, setMenuPosition ] = useState( { top: 0, left: 0 } );
	const menuRef = useRef( null );
	const triggerMatchRef = useRef( null );
	const selectedIndexRef = useRef( 0 );

	// Keep refs in sync with state for use in command handlers
	useEffect( () => {
		triggerMatchRef.current = triggerMatch;
	}, [ triggerMatch ] );

	useEffect( () => {
		selectedIndexRef.current = selectedIndex;
	}, [ selectedIndex ] );

	/**
	 * Insert the selected suggestion into the editor.
	 *
	 * @param {Object|string} suggestion - The suggestion to insert.
	 */
	const selectSuggestion = useCallback( ( suggestion ) => {
		const match = triggerMatchRef.current;
		if ( ! match ) {
			return;
		}

		editor.update( () => {
			const selection = $getSelection();
			if ( ! $isRangeSelection( selection ) ) {
				return;
			}

			const anchor = selection.anchor;
			const anchorNode = anchor.getNode();

			if ( anchorNode.getType() !== 'text' ) {
				return;
			}

			const textContent = anchorNode.getTextContent();
			const { trigger, startOffset, endOffset } = match;

			// Build replacement text based on trigger type
			let replacementText;

			switch ( trigger ) {
				case '@':
					// Author mention - use key (user_nicename) for the @mention
					// Server-side PHP will convert @key to a link
					replacementText = suggestion.key
						? `@${ suggestion.key } `
						: `@${ suggestion } `;
					break;
				case '#':
					// Hashtag - use name property
					// Server-side PHP will convert #hashtag to a link
					replacementText = suggestion.name
						? `#${ suggestion.name } `
						: `#${ suggestion } `;
					break;
				case '/':
					// Command - insert the command text
					replacementText = suggestion + ' ';
					break;
				case ':':
					// Emoji - insert :key: format (e.g., :smile:)
					// Server-side PHP will convert :key: to an emoji image
					replacementText = suggestion.key
						? `:${ suggestion.key }: `
						: `:${ suggestion }: `;
					break;
				default:
					replacementText = suggestion + ' ';
			}

			// Replace the trigger + search text with the replacement
			const beforeText = textContent.slice( 0, startOffset );
			const afterText = textContent.slice( endOffset );
			const newText = beforeText + replacementText + afterText;

			// Update the text node
			anchorNode.setTextContent( newText );

			// Move cursor to end of inserted text
			const newOffset = startOffset + replacementText.length;
			selection.anchor.set( anchorNode.getKey(), newOffset, 'text' );
			selection.focus.set( anchorNode.getKey(), newOffset, 'text' );
		} );

		setTriggerMatch( null );
	}, [ editor ] );

	// Register keyboard commands with Lexical
	useEffect( () => {
		const unregisterEnter = editor.registerCommand(
			KEY_ENTER_COMMAND,
			( event ) => {
				if ( triggerMatchRef.current && suggestions && suggestions.length > 0 ) {
					event.preventDefault();
					selectSuggestion( suggestions[ selectedIndexRef.current ] );
					return true;
				}
				return false;
			},
			COMMAND_PRIORITY_HIGH
		);

		const unregisterTab = editor.registerCommand(
			KEY_TAB_COMMAND,
			( event ) => {
				if ( triggerMatchRef.current && suggestions && suggestions.length > 0 ) {
					event.preventDefault();
					selectSuggestion( suggestions[ selectedIndexRef.current ] );
					return true;
				}
				return false;
			},
			COMMAND_PRIORITY_HIGH
		);

		const unregisterArrowDown = editor.registerCommand(
			KEY_ARROW_DOWN_COMMAND,
			( event ) => {
				if ( triggerMatchRef.current && suggestions && suggestions.length > 0 ) {
					event.preventDefault();
					setSelectedIndex( ( prev ) =>
						prev < suggestions.length - 1 ? prev + 1 : 0
					);
					return true;
				}
				return false;
			},
			COMMAND_PRIORITY_HIGH
		);

		const unregisterArrowUp = editor.registerCommand(
			KEY_ARROW_UP_COMMAND,
			( event ) => {
				if ( triggerMatchRef.current && suggestions && suggestions.length > 0 ) {
					event.preventDefault();
					setSelectedIndex( ( prev ) =>
						prev > 0 ? prev - 1 : suggestions.length - 1
					);
					return true;
				}
				return false;
			},
			COMMAND_PRIORITY_HIGH
		);

		const unregisterEscape = editor.registerCommand(
			KEY_ESCAPE_COMMAND,
			( event ) => {
				if ( triggerMatchRef.current ) {
					event.preventDefault();
					setTriggerMatch( null );
					return true;
				}
				return false;
			},
			COMMAND_PRIORITY_HIGH
		);

		return () => {
			unregisterEnter();
			unregisterTab();
			unregisterArrowDown();
			unregisterArrowUp();
			unregisterEscape();
		};
	}, [ editor, suggestions, selectSuggestion ] );

	// Update trigger match when content changes
	useEffect( () => {
		return editor.registerUpdateListener( ( { editorState } ) => {
			editorState.read( () => {
				const match = getTriggerMatch( editor );
				setTriggerMatch( match );

				if ( match && onSearch ) {
					onSearch( match.trigger, match.text );
				}

				// Reset selection when match changes
				if ( match ) {
					setSelectedIndex( 0 );
				}
			} );
		} );
	}, [ editor, onSearch ] );

	// Update menu position based on cursor
	useEffect( () => {
		if ( ! triggerMatch ) {
			return;
		}

		const selection = window.getSelection();
		if ( selection && selection.rangeCount > 0 ) {
			const range = selection.getRangeAt( 0 );
			const rect = range.getBoundingClientRect();

			// Position below the cursor
			setMenuPosition( {
				top: rect.bottom + 4,
				left: rect.left,
			} );
		}
	}, [ triggerMatch, suggestions ] );

	// Don't render if no trigger match or no suggestions
	if ( ! triggerMatch || ! suggestions || suggestions.length === 0 ) {
		return null;
	}

	return (
		<div
			ref={ menuRef }
			className="liveblog-autocomplete-menu"
			style={ {
				position: 'fixed',
				top: menuPosition.top,
				left: menuPosition.left,
				zIndex: 1000,
			} }
		>
			<ul className="liveblog-autocomplete-list">
				{ suggestions.map( ( suggestion, index ) => {
					// Handle different suggestion types
					let displayText;
					let avatarHtml = null;

					if ( typeof suggestion === 'string' ) {
						displayText = suggestion;
					} else if ( suggestion.image && suggestion.key ) {
						// Emoji - has image (unicode codepoint) and key (shortcode)
						displayText = `:${ suggestion.key }:`;
					} else if ( suggestion.name ) {
						// Author or hashtag
						displayText = suggestion.name;
						// Avatar is HTML string, not a URL
						if ( suggestion.avatar ) {
							avatarHtml = suggestion.avatar;
						}
					} else {
						displayText = String( suggestion );
					}

					return (
						<li
							key={ index }
							className={ `liveblog-autocomplete-item${
								index === selectedIndex ? ' is-selected' : ''
							}` }
							onClick={ () => selectSuggestion( suggestion ) }
							onMouseEnter={ () => setSelectedIndex( index ) }
						>
							{ avatarHtml && (
								<span
									className="liveblog-autocomplete-avatar"
									dangerouslySetInnerHTML={ { __html: avatarHtml } }
								/>
							) }
							<span className="liveblog-autocomplete-text">
								{ displayText }
							</span>
						</li>
					);
				} ) }
			</ul>
		</div>
	);
}

AutocompletePlugin.propTypes = {
	suggestions: PropTypes.array,
	onSearch: PropTypes.func,
};

// ============================================================================
// Toolbar Components
// ============================================================================

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
				title={ __( 'Add link', 'liveblog' ) }
			>
				<span className="dashicons dashicons-yes" />
			</button>
			<button
				type="button"
				className="liveblog-editor-btn liveblog-input-cancel"
				onClick={ onCancel }
				title={ __( 'Cancel', 'liveblog' ) }
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
		isQuote: false,
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

		// Check for quote
		if ( $isQuoteNode( element ) ) {
			state.isQuote = true;
		}

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
function ToolbarPlugin( { readOnly, handleImageUpload } ) {
	const [ editor ] = useLexicalComposerContext();
	const [ selectionState, setSelectionState ] = useState( {
		isBold: false,
		isItalic: false,
		isUnderline: false,
		isLink: false,
		isQuote: false,
		listType: null,
	} );
	const [ showLinkInput, setShowLinkInput ] = useState( false );
	const [ linkUrl, setLinkUrl ] = useState( 'https://' );
	const [ isUploading, setIsUploading ] = useState( false );
	const [ showCropModal, setShowCropModal ] = useState( false );
	const [ pendingImageFile, setPendingImageFile ] = useState( null );
	const fileInputRef = useRef( null );

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

	const formatQuote = useCallback( () => {
		editor.update( () => {
			const selection = $getSelection();
			if ( ! $isRangeSelection( selection ) ) {
				return;
			}

			if ( selectionState.isQuote ) {
				// Remove quote - convert back to paragraph
				$setBlocksType( selection, () => $createParagraphNode() );
			} else {
				// Add quote
				$setBlocksType( selection, () => $createQuoteNode() );
			}
		} );
	}, [ editor, selectionState.isQuote ] );

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

	const handleImageButtonClick = useCallback( () => {
		fileInputRef.current?.click();
	}, [] );

	const handleFileSelect = useCallback( ( event ) => {
		const file = event.target.files?.[ 0 ];
		if ( file && file.type.startsWith( 'image/' ) ) {
			// Show crop modal for images
			setPendingImageFile( file );
			setShowCropModal( true );
			// Reset input so same file can be selected again
			if ( fileInputRef.current ) {
				fileInputRef.current.value = '';
			}
		}
	}, [] );

	const handleCropComplete = useCallback( ( croppedFile ) => {
		setShowCropModal( false );
		setPendingImageFile( null );

		if ( handleImageUpload ) {
			setIsUploading( true );
			handleImageUpload( croppedFile )
				.then( ( src ) => {
					editor.dispatchCommand( INSERT_IMAGE_COMMAND, {
						src,
						alt: croppedFile.name,
					} );
				} )
				.catch( ( err ) => {
					// eslint-disable-next-line no-console
					console.error( 'Image upload failed:', err );
				} )
				.finally( () => {
					setIsUploading( false );
				} );
		}
	}, [ editor, handleImageUpload ] );

	const handleCropCancel = useCallback( () => {
		setShowCropModal( false );
		setPendingImageFile( null );
	}, [] );

	return (
		<div className="liveblog-editor-toolbar-container">
			<div className="liveblog-toolbar">
				<ToolbarButton
					onClick={ formatBold }
					icon="editor-bold"
					title={ __( 'Bold (Ctrl+B)', 'liveblog' ) }
					isActive={ selectionState.isBold }
					disabled={ readOnly }
				/>
				<ToolbarButton
					onClick={ formatItalic }
					icon="editor-italic"
					title={ __( 'Italic (Ctrl+I)', 'liveblog' ) }
					isActive={ selectionState.isItalic }
					disabled={ readOnly }
				/>
				<ToolbarButton
					onClick={ formatUnderline }
					icon="editor-underline"
					title={ __( 'Underline (Ctrl+U)', 'liveblog' ) }
					isActive={ selectionState.isUnderline }
					disabled={ readOnly }
				/>
				<ToolbarButton
					onClick={ formatOrderedList }
					icon="editor-ol"
					title={ __( 'Ordered List', 'liveblog' ) }
					isActive={ selectionState.listType === 'number' }
					disabled={ readOnly }
				/>
				<ToolbarButton
					onClick={ formatUnorderedList }
					icon="editor-ul"
					title={ __( 'Unordered List', 'liveblog' ) }
					isActive={ selectionState.listType === 'bullet' }
					disabled={ readOnly }
				/>
				<ToolbarButton
					onClick={ formatQuote }
					icon="format-quote"
					title={ __( 'Blockquote', 'liveblog' ) }
					isActive={ selectionState.isQuote }
					disabled={ readOnly }
				/>
				<div style={ { position: 'relative', display: 'inline-block' } }>
					<ToolbarButton
						onClick={ openLinkModal }
						icon="admin-links"
						title={ __( 'Add Link', 'liveblog' ) }
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
					title={ __( 'Remove Link', 'liveblog' ) }
					disabled={ readOnly || ! selectionState.isLink }
				/>
				{ handleImageUpload && (
					<>
						<ToolbarButton
							onClick={ handleImageButtonClick }
							icon="format-image"
							title={ isUploading ? __( 'Uploading…', 'liveblog' ) : __( 'Insert Image', 'liveblog' ) }
							disabled={ readOnly || isUploading }
						/>
						<input
							ref={ fileInputRef }
							type="file"
							accept="image/*"
							onChange={ handleFileSelect }
							style={ { display: 'none' } }
						/>
					</>
				) }
			</div>
			{ showCropModal && pendingImageFile && (
				<ImageCropModal
					imageFile={ pendingImageFile }
					onCropComplete={ handleCropComplete }
					onCancel={ handleCropCancel }
				/>
			) }
		</div>
	);
}

ToolbarPlugin.propTypes = {
	readOnly: PropTypes.bool,
	handleImageUpload: PropTypes.func,
};

// ============================================================================
// Main Component
// ============================================================================

/**
 * Main Lexical Editor component.
 *
 * @param {Object}   props                   - Component props.
 * @param {string}   props.initialContent    - Initial HTML content to load.
 * @param {Function} props.onChange          - Callback when content changes, receives HTML string.
 * @param {boolean}  props.readOnly          - Whether the editor is read-only.
 * @param {Function} props.handleImageUpload - Function to handle image uploads.
 * @param {Array}    props.suggestions       - Autocomplete suggestions to display.
 * @param {Function} props.onSearch          - Callback when autocomplete trigger detected.
 */
const LexicalEditor = ( {
	initialContent = '',
	onChange = null,
	readOnly = false,
	handleImageUpload = null,
	suggestions = [],
	onSearch = null,
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
				<ToolbarPlugin readOnly={ readOnly } handleImageUpload={ handleImageUpload } />
				<div className="liveblog-lexical-editor-inner">
					<RichTextPlugin
						contentEditable={
							<ContentEditable className="liveblog-lexical-content-editable" />
						}
						placeholder={
							<div className="liveblog-lexical-placeholder">
								{ __( 'Start writing your liveblog entry…', 'liveblog' ) }
							</div>
						}
						ErrorBoundary={ LexicalErrorBoundary }
					/>
				</div>
				<HistoryPlugin />
				<LinkPlugin />
				<ListPlugin />
				<ImagePlugin handleImageUpload={ handleImageUpload } />
				<AutocompletePlugin
					suggestions={ suggestions }
					onSearch={ onSearch }
				/>
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
	handleImageUpload: PropTypes.func,
	suggestions: PropTypes.array,
	onSearch: PropTypes.func,
};

export default LexicalEditor;
