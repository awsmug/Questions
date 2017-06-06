( function( torroBuilder ) {
	'use strict';

	/**
	 * A collection of containers.
	 *
	 * @class
	 * @augments torro.Builder.BaseCollection
	 */
	torroBuilder.ContainerCollection = torroBuilder.BaseCollection.extend({

		/**
		 * Model class for the container collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {function}
		 */
		model: torroBuilder.ContainerModel,

		/**
		 * REST endpoint URL part for accessing containers.
		 *
		 * @since 1.0.0
		 * @access public
		 * @type {string}
		 */
		urlEndpoint: 'containers',

		/**
		 * Default properties for the collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {object}
		 */
		defaultProps: {
			selected:          false,
			form_id:           0,
			label_placeholder: 'Page %s'
		},

		/**
		 * Returns container defaults.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {object} Container defaults.
		 */
		getDefaultAttributes: function() {
			return {
				form_id: this.props.get( 'form_id' ),
				label:   this.props.get( 'label_placeholder' ).replace( '%s', this.length + 1 ),
				sort:    this.length
			};
		}
	});

})( window.torro.Builder );