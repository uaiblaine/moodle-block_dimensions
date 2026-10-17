# Draw the plans' star outside the active bucket, whose cards alone carry a star.
s/ \|\| \(type === 'plan' && state\.planStatus !== 'active'\);/;/;
