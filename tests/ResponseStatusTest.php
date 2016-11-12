<?php

namespace Jasny\HttpMessage;

use Jasny\HttpMessage\ResponseStatus;
use PHPUnit_Framework_TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @covers Jasny\HttpMessage\ResposneStatus
 */
class ResponseStatusTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ResponseStatus|MockObject
     */
    protected $baseStatus;
    
    public function setUp()
    {
        $this->baseStatus = $this->getMockBuilder(ResponseStatus::class)
            ->setConstructorArgs(['1.1'])
            ->setMethods(['header', 'headersSent', 'httpResponseCode'])
            ->getMock();
        
        $this->baseStatus->method('headersSent')->willReturn(false);
    }
    
    
    public function testStatusCodeDefaults()
    {
        $this->assertSame(200, $this->baseStatus->getStatusCode());
        $this->assertSame('OK', $this->baseStatus->getReasonPhrase());
    }

    
    public function statusCodeProvider()
    {
        return [
            [404, null, 'Not Found'],
            [404, 'Some unique status', 'Some unique status'],
            [999, null, ''],
        ];
    }

    /**
     * @dataProvider statusCodeProvider
     * 
     * @param int    $status
     * @param string $phrase
     * @param string $expectPhrase
     */
    public function testConstruct($status, $phrase, $expectPhrase)
    {
        $responseStatus = new ResponseStatus('1.1', $status, $phrase);
        
        $this->assertSame($status, $responseStatus->getStatusCode());
        $this->assertSame($expectPhrase, $responseStatus->getReasonPhrase());
    }    
    
    /**
     * @dataProvider statusCodeProvider
     * 
     * @param int    $status
     * @param string $phrase
     * @param string $expectPhrase
     */
    public function testWithStatus($status, $phrase, $expectPhrase)
    {
        $responseStatus = $this->baseStatus->withStatus($status, $phrase);
        
        $this->assertInstanceOf(ResponseStatus::class, $responseStatus);
        $this->assertNotSame($this->baseStatus, $responseStatus);
        
        $this->assertSame($status, $responseStatus->getStatusCode());
        $this->assertSame($expectPhrase, $responseStatus->getReasonPhrase());
        
        $this->assertFalse($this->baseStatus->isStale());
    }
    
    public function testWithStatusSame()
    {
        $status = $this->baseStatus->withStatus(200);
        $finalStatus = $status->withStatus(200);
        
        $this->assertSame($status, $finalStatus);
    }
    
    /**
     * @dataProvider statusCodeProvider
     * 
     * @param int    $status
     * @param string $phrase
     * @param string $expectPhrase
     */
    public function testWithStatusGlobal($status, $phrase, $expectPhrase)
    {
        $this->baseStatus->expects($this->once())->method('header')->with("HTTP/1.1 {$status} $expectPhrase");
        $this->baseStatus->expects($this->any())->method('httpResponseCode')->willReturn($status);
        $this->baseStatus->useGlobally();
        
        $responseStatus = $this->baseStatus->withStatus($status, $phrase);
        
        $this->assertInstanceOf(ResponseStatus::class, $responseStatus);
        $this->assertNotSame($this->baseStatus, $responseStatus);

        $this->assertSame($status, $responseStatus->getStatusCode());
        $this->assertSame($expectPhrase, $responseStatus->getReasonPhrase());
        
        $this->assertTrue($this->baseStatus->isStale());
    }
    
    public function testGetStatusGlobal()
    {
        $this->baseStatus->expects($this->any())->method('httpResponseCode')->willReturn(400);
        $this->baseStatus->useGlobally();
        
        $this->assertSame(400, $this->baseStatus->getStatusCode());
        $this->assertSame('Bad Request', $this->baseStatus->getReasonPhrase());
    }
    
    
    public function testUseGloballyWhenStale()
    {
        $this->baseStatus->useGlobally();
        $this->baseStatus->withStatus(200);
        
        $this->baseStatus->useGlobally();
        $this->assertTrue($this->baseStatus->isStale());
    }
    
    public function testUseLocally()
    {
        $this->baseStatus->useGlobally();
        
        $this->baseStatus->expects($this->once())->method('httpResponseCode')->willReturn(400);
        $this->baseStatus->useLocally();
        
        $this->assertEquals(400, $this->baseStatus->getStatusCode());
        $this->assertSame('Bad Request', $this->baseStatus->getReasonPhrase());
        
        $this->baseStatus->useLocally();
    }

    public function testUseLocallyNoStatus()
    {
        $this->baseStatus->useGlobally();
        $status = $this->baseStatus->withStatus(400);
        
        $status->expects($this->once())->method('httpResponseCode')->willReturn(false);
        $status->useLocally();
        
        $this->assertEquals(200, $status->getStatusCode());
        $this->assertSame('OK', $status->getReasonPhrase());
        
        $status->useLocally();
    }
    
    public function testUseGlobally()
    {
        $this->baseStatus->expects($this->once())->method('header')->with("HTTP/1.1 201 Created");
        
        $status = $this->baseStatus->withStatus(201);
        $status->useGlobally();
    }
    
    
    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidTypeStatusCode()
    {
        $this->baseStatus->withStatus(1020.20);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidValueStatusCode()
    {
        $this->baseStatus->withStatus(1020);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidValueStatusPhrase()
    {
        $this->baseStatus->withStatus(200, ['foo', 'bar']);
    }
    
    
    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructInvalidTypeProtocolVersion()
    {
        new ResponseStatus(['woo']);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructInvalidTypeStatusCode()
    {
        new ResponseStatus('1.1', 1020.20);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructInvalidValueStatusCode()
    {
        new ResponseStatus('1.1', 1020);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructInvalidValueStatusPhrase()
    {
        new ResponseStatus('1.1', 200, ['foo', 'bar']);
    }
    
    
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Can not change stale object
     */
    public function testModifyStale()
    {
        $this->baseStatus->useGlobally();
        
        $this->baseStatus->withStatus(200);
        $this->baseStatus->withStatus(400);
    }
    
    
    public function testWithProtocolVersion()
    {
        $this->baseStatus->expects($this->once())->method('header')->with("HTTP/2 200 OK");
        
        $responseStatus = $this->baseStatus->withProtocolVersion('2');
        $responseStatus->useGlobally();
        
        $responseStatus->withStatus(200);
    }
    
    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithProtocolVerionsInvalidArgument()
    {
        $this->baseStatus->withProtocolVersion(0);
    }
}