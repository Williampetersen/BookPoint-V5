import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useMemo, useState } from '@wordpress/element';

import metadata from './block.json';

registerBlockType(metadata.name, {
  ...metadata,

  edit: ({ attributes, setAttributes }) => {
    const serviceId = attributes.serviceId || 0;

    const [services, setServices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');

    useEffect(() => {
      let isMounted = true;

      setLoading(true);
      setLoadError('');

      apiFetch({ path: '/pointly-booking/v1/services' })
        .then((res) => {
          if (!isMounted) return;
          const list = (res && res.data) ? res.data : [];
          setServices(Array.isArray(list) ? list : []);
        })
        .catch((err) => {
          if (!isMounted) return;
          setLoadError(err?.message || 'Failed to load services');
        })
        .finally(() => {
          if (!isMounted) return;
          setLoading(false);
        });

      return () => { isMounted = false; };
    }, []);

    const options = useMemo(() => {
      const base = [{ label: __('Select a serviceÃ¢â‚¬Â¦', 'bookpoint-booking'), value: '0' }];
      const mapped = services.map((s) => ({
        label: `${s.name} (${s.duration_minutes} min)`,
        value: String(s.id),
      }));
      return base.concat(mapped);
    }, [services]);

    const selectedLabel = useMemo(() => {
      const found = services.find((s) => Number(s.id) === Number(serviceId));
      return found ? found.name : '';
    }, [services, serviceId]);

    return (
      <>
        <InspectorControls>
          <PanelBody title={__('BookPoint Settings', 'bookpoint-booking')} initialOpen={true}>

            {loading && (
              <Notice status="info" isDismissible={false}>
                {__('Loading servicesÃ¢â‚¬Â¦', 'bookpoint-booking')}
              </Notice>
            )}

            {!!loadError && (
              <Notice status="error" isDismissible={false}>
                {__('Could not load services:', 'bookpoint-booking')} {loadError}
              </Notice>
            )}

            <SelectControl
              label={__('Service', 'bookpoint-booking')}
              value={String(serviceId || 0)}
              options={options}
              onChange={(val) => setAttributes({ serviceId: parseInt(val || '0', 10) || 0 })}
              help={__('Services are loaded from BookPoint Ã¢â€ â€™ Services.', 'bookpoint-booking')}
            />

            <TextControl
              label={__('Default Date (YYYY-MM-DD)', 'bookpoint-booking')}
              value={attributes.defaultDate || ''}
              onChange={(val) => setAttributes({ defaultDate: val || '' })}
              placeholder="2026-01-19"
            />

            <ToggleControl
              label={__('Hide Notes Field', 'bookpoint-booking')}
              checked={!!attributes.hideNotes}
              onChange={(val) => setAttributes({ hideNotes: !!val })}
            />

            <ToggleControl
              label={__('Require Phone', 'bookpoint-booking')}
              checked={!!attributes.requirePhone}
              onChange={(val) => setAttributes({ requirePhone: !!val })}
            />

            <ToggleControl
              label={__('Compact Layout', 'bookpoint-booking')}
              checked={!!attributes.compact}
              onChange={(val) => setAttributes({ compact: !!val })}
            />

          </PanelBody>
        </InspectorControls>

        <div style={{ padding: '14px', border: '1px solid #ddd', borderRadius: '6px' }}>
          <strong>{__('BookPoint Ã¢â‚¬â€œ Booking Form', 'bookpoint-booking')}</strong>

          {serviceId > 0 ? (
            <p style={{ marginTop: '8px' }}>
              {__('This block will render the booking form for:', 'bookpoint-booking')}{' '}
              <code>{selectedLabel || `#${serviceId}`}</code>
            </p>
          ) : (
            <Notice status="warning" isDismissible={false}>
              {__('Please choose a service in the block settings.', 'bookpoint-booking')}
            </Notice>
          )}
        </div>
      </>
    );
  },

  save: () => null
});
