/* global wp, leastudiosFormsBlock */
(function () {
	'use strict';

	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var SelectControl = wp.components.SelectControl;
	var Placeholder = wp.components.Placeholder;
	var ServerSideRender = wp.serverSideRender || wp.components.ServerSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;

	registerBlockType('leastudios-forms/form', {
		title: 'leaStudios Form',
		description: 'Display a leaStudios form.',
		icon: 'feedback',
		category: 'widgets',
		attributes: {
			formId: {
				type: 'number',
				default: 0
			}
		},

		edit: function (props) {
			var blockProps = useBlockProps();
			var formId = props.attributes.formId;
			var forms = leastudiosFormsBlock.forms || [];

			var options = [{ label: '— Select a form —', value: 0 }];
			forms.forEach(function (form) {
				options.push({ label: form.title, value: form.id });
			});

			if (!formId) {
				return el('div', blockProps,
					el(Placeholder, {
						icon: 'feedback',
						label: 'leaStudios Form',
						instructions: 'Select a form to display.'
					},
						el(SelectControl, {
							value: formId,
							options: options,
							onChange: function (val) {
								props.setAttributes({ formId: parseInt(val, 10) });
							}
						})
					)
				);
			}

			return el('div', blockProps,
				el(SelectControl, {
					label: 'Form',
					value: formId,
					options: options,
					onChange: function (val) {
						props.setAttributes({ formId: parseInt(val, 10) });
					},
					style: { marginBottom: '12px' }
				}),
				el(ServerSideRender, {
					block: 'leastudios-forms/form',
					attributes: props.attributes
				})
			);
		},

		save: function () {
			return null;
		}
	});
})();
