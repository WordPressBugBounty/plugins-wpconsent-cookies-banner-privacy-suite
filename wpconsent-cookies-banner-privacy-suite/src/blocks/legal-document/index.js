/**
 * The Legal Document block: a server-rendered stored document with a
 * document-type picker in the sidebar. Rendering happens in PHP, so the
 * editor shows exactly what the page shows. The strings come from PHP too
 * (wpconsentLegalDocumentBlock), translated under the plugin's own text domain.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

const L = window.wpconsentLegalDocumentBlock || {};
const DOCUMENTS = Object.keys( L.documents || {} ).map( ( value ) => ( { value, label: L.documents[ value ] } ) );

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();
		return (
			<>
				<InspectorControls>
					<PanelBody title={ L.panel || 'Document' }>
						<SelectControl
							label={ L.which || 'Which document' }
							value={ attributes.document }
							options={ DOCUMENTS }
							onChange={ ( document ) => setAttributes( { document } ) }
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps }>
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
						EmptyResponsePlaceholder={ () => <p>{ L.empty || 'No published document yet.' }</p> }
					/>
				</div>
			</>
		);
	},
	save: () => null,
} );
