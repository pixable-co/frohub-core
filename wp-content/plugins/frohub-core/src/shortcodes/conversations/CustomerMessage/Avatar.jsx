// Changes to Avatar.jsx to match the screenshot design
import React from 'react';

const Avatar = ({ name, size = 'md' }) => {
    const getInitials = (name) => {
        return name?.split(' ').map(n => n[0]).join('').toUpperCase() || '?';
    };

    const sizeClasses = {
        sm: 'w-8 h-8 text-xs',
        md: 'w-8 h-8 text-xs',
        lg: 'w-10 h-10 text-sm'
    };

    return (
        <div className={`${sizeClasses[size]} bg-gray-300 rounded-full flex items-center justify-center text-gray-500`}>
            {getInitials(name)}
        </div>
    );
};

export default Avatar;
