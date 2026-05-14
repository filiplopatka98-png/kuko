<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\FakeMailer;
use PHPUnit\Framework\TestCase;

final class FakeMailerTest extends TestCase
{
    public function testRecordsSends(): void
    {
        $m = new FakeMailer();
        $this->assertTrue($m->send('a@b.com', 'sub', '<p>x</p>', 'x'));
        $this->assertCount(1, $m->sent);
        $this->assertSame('a@b.com', $m->sent[0]['to']);
        $this->assertSame('sub', $m->sent[0]['subject']);
    }

    public function testFailsWhenFlagged(): void
    {
        $m = new FakeMailer();
        $m->shouldFail = true;
        $this->assertFalse($m->send('a@b.com', 'x', 'x', 'x'));
        $this->assertCount(0, $m->sent);
    }

    public function testReplyToCaptured(): void
    {
        $m = new FakeMailer();
        $m->send('a@b.com', 's', 'h', 't', 'reply@x.sk');
        $this->assertSame('reply@x.sk', $m->sent[0]['replyTo']);
    }
}
