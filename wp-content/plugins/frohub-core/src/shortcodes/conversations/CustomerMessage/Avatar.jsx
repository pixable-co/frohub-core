import React from 'react';

const Avatar = ({ name, size = 'md' }) => {
    const getInitials = (name) => {
        return name?.split(' ').map(n => n[0]).join('').toUpperCase() || '?';
    };

    const sizeClasses = {
        sm: 'w-8 h-8 text-xs',
        md: 'w-10 h-10 text-sm',
        lg: 'w-12 h-12 text-base'
    };

    return (
        <div className={`${sizeClasses[size]} bg-gray-400 rounded-full flex items-center justify-center text-white font-medium`}>
            {getInitials(name)}
        </div>
    );
};

export default Avatar;