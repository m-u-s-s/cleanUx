import React from 'react';
import { render } from '@testing-library/react-native';
import { Button } from '@/ui/Button';
import { Badge } from '@/ui/Badge';
import { Avatar } from '@/ui/Avatar';
import { Tag } from '@/ui/Tag';
import { Divider } from '@/ui/Divider';
import { TextInput } from '@/ui/TextInput';

describe('UI atoms', () => {
  it('Button renders with label', () => {
    const { getByText } = render(<Button label="Click me" onPress={() => {}} />);
    expect(getByText('Click me')).toBeTruthy();
  });

  it('Button shows loading indicator', () => {
    const { queryByText } = render(<Button label="Save" onPress={() => {}} loading />);
    expect(queryByText('Save')).toBeTruthy();
  });

  it('Badge renders label with variant', () => {
    const { getByText } = render(<Badge label="New" variant="success" />);
    expect(getByText('New')).toBeTruthy();
  });

  it('Avatar shows initials when no image', () => {
    const { getByText } = render(<Avatar name="John Doe" />);
    expect(getByText('JD')).toBeTruthy();
  });

  it('Tag renders label', () => {
    const { getByText } = render(<Tag label="Urgent" variant="urgent" />);
    expect(getByText('Urgent')).toBeTruthy();
  });

  it('Divider renders with label', () => {
    const { getByText } = render(<Divider label="or" />);
    expect(getByText('or')).toBeTruthy();
  });

  it('TextInput renders label and error', () => {
    const { getByText } = render(<TextInput label="Email" error="Required" />);
    expect(getByText('Email')).toBeTruthy();
    expect(getByText('Required')).toBeTruthy();
  });
});
