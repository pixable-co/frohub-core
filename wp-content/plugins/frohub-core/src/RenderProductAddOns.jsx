import { useEffect, useState } from 'react';

export default function RenderProductAddOns() {
    const [attributes, setAttributes] = useState([]);

    useEffect(() => {
        const fetchAttributes = async () => {
            const response = await fetch('/wp-json/frohub/v1/product-attributes');
            const data = await response.json();
            setAttributes(data);
        };

        fetchAttributes();
    }, []);

    return (
        <div>
            {attributes.length > 0 ? (
                <ul>
                    {attributes.map((attr) => (
                        <li key={attr.id}>{attr.name}: {attr.value}</li>
                    ))}
                </ul>
            ) : (
                <p>No attributes found.</p>
            )}
        </div>
    );
}