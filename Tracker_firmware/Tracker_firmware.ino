
// MPU-9150 Accelerometer + Gyro + Compass + Temperature
// -----------------------------
//
// By arduino.cc user "frtrobotik" (Tobias HÃ¼bner)
//
//
// July 2013
//      first version
//
// Somewhere around 2014
// 	Jimmy crudely hacked it to log gps/sensor data
//	in json format on SD-Card.
//
// Open Source / Public Domain
//
// Using Arduino 1.0.1
// It will not work with an older version,
// since Wire.endTransmission() uses a parameter
// to hold or release the I2C bus.
//
// Documentation:
// - The InvenSense documents:
//   - "MPU-9150 Product Specification Revision 4.0",
//     PS-MPU-9150A.pdf
//   - "MPU-9150 Register Map and Descriptions Revision 4.0",
//     RM-MPU-9150A-00.pdf
//   - "MPU-9150 9-Axis Evaluation Board User Guide"
//     AN-MPU-9150EVB-00.pdf
//
// The accuracy is 16-bits.
//
// Some parts are copied by the MPU-6050 Playground page.
// playground.arduino.cc/Main/MPU-6050
// There are more Registervalues. Here are only the most
// nessecary ones to get started with this sensor.

#include <Wire.h>
#include <SD.h>

#define PIN_SD_SELECT 10
#define PIN_STATUS_LED 6
#define PIN_GPS_LED 9
#define PIN_BTN_INPUT 7
#define PIN_SPEED_INPUT 8
#define MAX_SAMPLES_PER_FILE 250000L

// Register names according to the datasheet.
// According to the InvenSense document
// "MPU-9150 Register Map and Descriptions Revision 4.0",

#define MPU9150_SELF_TEST_X        0x0D   // R/W
#define MPU9150_SELF_TEST_Y        0x0E   // R/W
#define MPU9150_SELF_TEST_X        0x0F   // R/W
#define MPU9150_SELF_TEST_A        0x10   // R/W
#define MPU9150_SMPLRT_DIV         0x19   // R/W
#define MPU9150_CONFIG             0x1A   // R/W
#define MPU9150_GYRO_CONFIG        0x1B   // R/W
#define MPU9150_ACCEL_CONFIG       0x1C   // R/W
#define MPU9150_FF_THR             0x1D   // R/W
#define MPU9150_FF_DUR             0x1E   // R/W
#define MPU9150_MOT_THR            0x1F   // R/W
#define MPU9150_MOT_DUR            0x20   // R/W
#define MPU9150_ZRMOT_THR          0x21   // R/W
#define MPU9150_ZRMOT_DUR          0x22   // R/W
#define MPU9150_FIFO_EN            0x23   // R/W
#define MPU9150_I2C_MST_CTRL       0x24   // R/W
#define MPU9150_I2C_SLV0_ADDR      0x25   // R/W
#define MPU9150_I2C_SLV0_REG       0x26   // R/W
#define MPU9150_I2C_SLV0_CTRL      0x27   // R/W
#define MPU9150_I2C_SLV1_ADDR      0x28   // R/W
#define MPU9150_I2C_SLV1_REG       0x29   // R/W
#define MPU9150_I2C_SLV1_CTRL      0x2A   // R/W
#define MPU9150_I2C_SLV2_ADDR      0x2B   // R/W
#define MPU9150_I2C_SLV2_REG       0x2C   // R/W
#define MPU9150_I2C_SLV2_CTRL      0x2D   // R/W
#define MPU9150_I2C_SLV3_ADDR      0x2E   // R/W
#define MPU9150_I2C_SLV3_REG       0x2F   // R/W
#define MPU9150_I2C_SLV3_CTRL      0x30   // R/W
#define MPU9150_I2C_SLV4_ADDR      0x31   // R/W
#define MPU9150_I2C_SLV4_REG       0x32   // R/W
#define MPU9150_I2C_SLV4_DO        0x33   // R/W
#define MPU9150_I2C_SLV4_CTRL      0x34   // R/W
#define MPU9150_I2C_SLV4_DI        0x35   // R  
#define MPU9150_I2C_MST_STATUS     0x36   // R
#define MPU9150_INT_PIN_CFG        0x37   // R/W
#define MPU9150_INT_ENABLE         0x38   // R/W
#define MPU9150_INT_STATUS         0x3A   // R  
#define MPU9150_ACCEL_XOUT_H       0x3B   // R  
#define MPU9150_ACCEL_XOUT_L       0x3C   // R  
#define MPU9150_ACCEL_YOUT_H       0x3D   // R  
#define MPU9150_ACCEL_YOUT_L       0x3E   // R  
#define MPU9150_ACCEL_ZOUT_H       0x3F   // R  
#define MPU9150_ACCEL_ZOUT_L       0x40   // R  
#define MPU9150_TEMP_OUT_H         0x41   // R  
#define MPU9150_TEMP_OUT_L         0x42   // R  
#define MPU9150_GYRO_XOUT_H        0x43   // R  
#define MPU9150_GYRO_XOUT_L        0x44   // R  
#define MPU9150_GYRO_YOUT_H        0x45   // R  
#define MPU9150_GYRO_YOUT_L        0x46   // R  
#define MPU9150_GYRO_ZOUT_H        0x47   // R  
#define MPU9150_GYRO_ZOUT_L        0x48   // R  
#define MPU9150_EXT_SENS_DATA_00   0x49   // R  
#define MPU9150_EXT_SENS_DATA_01   0x4A   // R  
#define MPU9150_EXT_SENS_DATA_02   0x4B   // R  
#define MPU9150_EXT_SENS_DATA_03   0x4C   // R  
#define MPU9150_EXT_SENS_DATA_04   0x4D   // R  
#define MPU9150_EXT_SENS_DATA_05   0x4E   // R  
#define MPU9150_EXT_SENS_DATA_06   0x4F   // R  
#define MPU9150_EXT_SENS_DATA_07   0x50   // R  
#define MPU9150_EXT_SENS_DATA_08   0x51   // R  
#define MPU9150_EXT_SENS_DATA_09   0x52   // R  
#define MPU9150_EXT_SENS_DATA_10   0x53   // R  
#define MPU9150_EXT_SENS_DATA_11   0x54   // R  
#define MPU9150_EXT_SENS_DATA_12   0x55   // R  
#define MPU9150_EXT_SENS_DATA_13   0x56   // R  
#define MPU9150_EXT_SENS_DATA_14   0x57   // R  
#define MPU9150_EXT_SENS_DATA_15   0x58   // R  
#define MPU9150_EXT_SENS_DATA_16   0x59   // R  
#define MPU9150_EXT_SENS_DATA_17   0x5A   // R  
#define MPU9150_EXT_SENS_DATA_18   0x5B   // R  
#define MPU9150_EXT_SENS_DATA_19   0x5C   // R  
#define MPU9150_EXT_SENS_DATA_20   0x5D   // R  
#define MPU9150_EXT_SENS_DATA_21   0x5E   // R  
#define MPU9150_EXT_SENS_DATA_22   0x5F   // R  
#define MPU9150_EXT_SENS_DATA_23   0x60   // R  
#define MPU9150_MOT_DETECT_STATUS  0x61   // R  
#define MPU9150_I2C_SLV0_DO        0x63   // R/W
#define MPU9150_I2C_SLV1_DO        0x64   // R/W
#define MPU9150_I2C_SLV2_DO        0x65   // R/W
#define MPU9150_I2C_SLV3_DO        0x66   // R/W
#define MPU9150_I2C_MST_DELAY_CTRL 0x67   // R/W
#define MPU9150_SIGNAL_PATH_RESET  0x68   // R/W
#define MPU9150_MOT_DETECT_CTRL    0x69   // R/W
#define MPU9150_USER_CTRL          0x6A   // R/W
#define MPU9150_PWR_MGMT_1         0x6B   // R/W
#define MPU9150_PWR_MGMT_2         0x6C   // R/W
#define MPU9150_FIFO_COUNTH        0x72   // R/W
#define MPU9150_FIFO_COUNTL        0x73   // R/W
#define MPU9150_FIFO_R_W           0x74   // R/W
#define MPU9150_WHO_AM_I           0x75   // R

//MPU9150 Compass
#define MPU9150_CMPS_XOUT_L        0x4A   // R
#define MPU9150_CMPS_XOUT_H        0x4B   // R
#define MPU9150_CMPS_YOUT_L        0x4C   // R
#define MPU9150_CMPS_YOUT_H        0x4D   // R
#define MPU9150_CMPS_ZOUT_L        0x4E   // R
#define MPU9150_CMPS_ZOUT_H        0x4F   // R


// I2C address 0x69 could be 0x68 depends on your wiring. 
int MPU9150_I2C_ADDRESS = 0x68;

File logFile;

//Variables where our values can be stored
int cmps[3];
int accl[3];
int gyro[3];
double temp;

char fileName[8];

long numSamples=0L;
uint8_t state=0;
uint8_t beginRecordHack=0;
uint8_t curSpeed;


void blinkError()
{
  while(1)
  {
    digitalWrite(6,1);
    delay(80);
    digitalWrite(6,0);
    delay(80);

  }
}

char gpsStr[256];
void readGps()
{
  uint8_t p=0;
  bool gotSeq=0;
  uint8_t field=0;
  uint8_t ptr;
  while(1)
  {

    if( Serial1.available())
    {
      char c = Serial1.read();
      if( p==0 && c=='$' )
      {
        p++;
      } 
      else if( p==1 && c=='G' )
      {
        p++;
      } 
      else if( p==2 && c=='P' )
      {
        p++;
      } 
      else if( p==3 && c=='R' )
      {
        p++;
      } 
      else if( p==4 && c=='M' )
      {
        p++;
      } 
      else if( p==5 && c=='C' )
      {
        p++;
        gotSeq=1;
        field=0;        
      } 
      else {
        if( gotSeq )
        {
          if( c==',' )
          {
            //Check what field
            if(field==0)
            {
              ptr=0;
              sprintf(&gpsStr[ptr], "{\"T\":\""); //Time
              ptr=6;
            } 
            else if( field==1 )
            {
              sprintf(&gpsStr[ptr], "\",\"S\":\""); //Status
              ptr+=7;
            } 
            else if( field==2 )
            {
              sprintf(&gpsStr[ptr], "\",\"Lt\":\""); //Latitude
              ptr+=8;
            } 
            else if( field==3 )
            {
              sprintf(&gpsStr[ptr], "\",\"AD\":\""); //Latitude dir
              ptr+=8;
            } 
            else if( field==4 )
            {
              sprintf(&gpsStr[ptr], "\",\"Ln\":\""); //Longitute
              ptr+=8;
            } 
            else if( field==5 )
            {
              sprintf(&gpsStr[ptr], "\",\"OD\":\""); //Longitude dir
              ptr+=8;
            } 
            else if( field==6 )
            {
              sprintf(&gpsStr[ptr], "\",\"K\":\""); //Knots
              ptr+=7;
            } 
            else if( field==7 )
            {
              sprintf(&gpsStr[ptr], "\",\"A\":\""); //Track Angle in degrees True
              ptr+=7;
            } 
            else if( field==8 )
            {
              sprintf(&gpsStr[ptr], "\",\"D\":\""); //Date
              ptr+=7;
            } 
            else if( field==9 )
            {
              sprintf(&gpsStr[ptr], "\",\"M\":\""); //Magnetic variation
              ptr+=7;
            } 
            else if( field==10 )
            {
              sprintf(&gpsStr[ptr], "\",\"MD\":\"");  //Magnetic variation Dir
              ptr+=8;
            } 
            else if( field==11 )
            {
              sprintf(&gpsStr[ptr], "\",\"CHK\":\""); //Checksum
              ptr+=9;
            }
            field++;
          } 
          else if(c != '\r') {
            gpsStr[ptr++]=c;

            if( field==2 )
            {
              if( c=='A' )
              {
                digitalWrite(PIN_GPS_LED,1); //Turn off search led when we got it
              } 
              else {
                digitalWrite(PIN_GPS_LED,0);  //Turn on led while searching

              }
            }

          }
          if(c == '\r')
          {
            sprintf(&gpsStr[ptr], "\"}");
            ptr+=2;
            gpsStr[ptr]=0;
            // Serial.print("Returning str: ");
            // Serial.println(gpsStr);

            return;
          }
        } 
        else {
          p=0;
        }

      }
    }
  }
}

void setGpsSpeed(  )
{
  //All multi-byte values are ordered in Little Endian format, unless otherwise indicated.
#define CKA 12
#define CKB 13
  // 4 hz
  //byte msg[] = { 0xB5, 0x62, 0x06, 0x08, 0x06, 0x00,  0xFA,0x00, 0x01,0x00, 0x00,0x00, 0x00,0x00 };
  // 5 hz = C8
  //                                                  SpdL SpdH
  byte msg[] = { 0xB5, 0x62, 0x06, 0x08, 0x06, 0x00,  0x00,0x00, 0x01,0x00, 0x00,0x00, 0x00,0x00  };
  
  if( curSpeed == 1 ) //Slow speed 0.5 hz
  {
    msg[6] = 0xD0;
    msg[7] = 0x07;
  } else { //Fast speed 5 hz
    msg[6] = 0xC8;
    msg[7] = 0x00;
  }

  for(int i=2; i<CKA;i++)
  {
    msg[CKA] += msg[i];
    msg[CKB] += msg[CKA];
  }
  delay(500);
  for(int i=0; i<sizeof(msg);i++)
  {
    Serial1.write(msg[i]);
  }


}

uint8_t debounce(uint8_t expected)
{
  uint8_t t=0;
  while( digitalRead( PIN_BTN_INPUT ) == expected )
  {
    delay(20);
    t++;
    if( t==5 )
    {
      return(1);
    }
  }
  return(0);
}

uint8_t btnLastState;

uint8_t btnPress()
{
  bool ret=0;
  uint8_t state = digitalRead(PIN_BTN_INPUT);

  if( btnLastState != state)
  {
    if( debounce(state) )
    {
      btnLastState=state;
      ret=1;
    }
  }

  return(ret);
}

void setup()
{      

  Serial1.begin(9600);
  /*  
   Serial.begin(9600);
   while(!Serial)
   {
   asm("nop");
   }
   Serial.print("Hej\n");
   while(1)
   {
   if(Serial1.available())
   {
   Serial.write( Serial1.read() );
   }
   }
   */
  //Sd pin
  pinMode(PIN_SD_SELECT, OUTPUT);



  pinMode(PIN_STATUS_LED,OUTPUT);
  analogWrite(PIN_STATUS_LED, 192);

  pinMode(PIN_BTN_INPUT,INPUT);
  //Internal pullups
  digitalWrite(PIN_BTN_INPUT,1);
  
  pinMode(PIN_SPEED_INPUT, INPUT);
  digitalWrite(PIN_SPEED_INPUT, 1);
  
  curSpeed = digitalRead( PIN_SPEED_INPUT );


  pinMode(PIN_GPS_LED, OUTPUT);
  digitalWrite(PIN_GPS_LED,0); //Search light on

  fileName[0] = '0';
  fileName[1] = '0';
  fileName[2] = '0';
  fileName[3] = '.';
  fileName[4] = 'T';
  fileName[5] = 'X';
  fileName[6] = 'T';
  fileName[7] = 0;

  do  {
    delay(100);  
  } 
  while(!SD.begin(10));

  delay(100);
  digitalWrite(PIN_STATUS_LED,1);



  Wire.begin();

  // Clear the 'sleep' bit to start the sensor.
  MPU9150_writeSensor(MPU9150_PWR_MGMT_1, 0);

  MPU9150_setupCompass();

  delay(500);

  curSpeed = digitalRead( PIN_SPEED_INPUT );
  setGpsSpeed();

  while( digitalRead( PIN_BTN_INPUT ) == 1 )
  {
    digitalWrite(PIN_GPS_LED, 1);
    delay(100);  
    digitalWrite(PIN_GPS_LED, 0);
    delay(100);
  }

  btnLastState=0;

}


void loop()
{

  if( digitalRead(PIN_SPEED_INPUT) != curSpeed )
  {
    curSpeed=digitalRead(PIN_SPEED_INPUT);
    setGpsSpeed();
  }

  if(state == 0 )
  {

    digitalWrite( PIN_STATUS_LED,0 ); //Turn on status led

    readGps();
    if( btnPress() || beginRecordHack )
    {
      state++;
      beginRecordHack=0;
      digitalWrite(6,1);
      //Find free fileName
      int attempt=0;
      while( SD.exists( fileName ) )
      {
        fileName[2]++;
        if(fileName[2] > '9')
        {
          fileName[2]='0';
          fileName[1]++;
          if( fileName[1] > '9' )
          {
            fileName[1]='0';
            fileName[0]++;
            if( fileName[0] > '9' )
            {
              blinkError();
            }
          }
        }
      }

      logFile  = SD.open(fileName, FILE_WRITE);
      if(!logFile)
      {
        blinkError();
      }

      numSamples=0;

      logFile.println("{ \"Samples\":[");
      gpsStr[0]=0;

    }

  } 
  else if(state == 1 )
  {

    if( numSamples != 0 )
    {
      logFile.print(",");
    } 
    numSamples++;
    

    if( !Serial1.available() )
    {
      temp = ( (double) MPU9150_readSensor(MPU9150_TEMP_OUT_L,MPU9150_TEMP_OUT_H) + 12412.0) / 340.0;
      cmps[0] = MPU9150_readSensor(MPU9150_CMPS_XOUT_L,MPU9150_CMPS_XOUT_H);
      cmps[1] = MPU9150_readSensor(MPU9150_CMPS_YOUT_L,MPU9150_CMPS_YOUT_H);
      cmps[2] = MPU9150_readSensor(MPU9150_CMPS_ZOUT_L,MPU9150_CMPS_ZOUT_H);
  
      gyro[0] = MPU9150_readSensor(MPU9150_GYRO_XOUT_L,MPU9150_GYRO_XOUT_H);
      gyro[1] = MPU9150_readSensor(MPU9150_GYRO_YOUT_L,MPU9150_GYRO_YOUT_H);
      gyro[2] = MPU9150_readSensor(MPU9150_GYRO_ZOUT_L,MPU9150_GYRO_ZOUT_H);
  
      accl[0] = MPU9150_readSensor(MPU9150_ACCEL_XOUT_L,MPU9150_ACCEL_XOUT_H);
      accl[1] = MPU9150_readSensor(MPU9150_ACCEL_YOUT_L,MPU9150_ACCEL_YOUT_H);
      accl[2] = MPU9150_readSensor(MPU9150_ACCEL_ZOUT_L,MPU9150_ACCEL_ZOUT_H);
      
      logFile.print("{\"S\":1,\"TM\":");
      logFile.print(temp);
  
      logFile.print(",\"CP\":{\"x\":");
      logFile.print(cmps[0]);
  
      logFile.print(",\"y\":");
      logFile.print(cmps[1]);
      logFile.print(",\"z\":");
      logFile.print(cmps[2]);
      logFile.print("}");
  
      logFile.print(",\"GY\":{\"x\":");
      logFile.print(gyro[0]);
      logFile.print(",\"y\":");
      logFile.print(gyro[1]);
      logFile.print(",\"z\":");
      logFile.print(gyro[2]);
      logFile.print("}");  
  
      logFile.print(",\"AC\":{\"x\":");
      logFile.print(accl[0]);
      logFile.print(",\"y\":");
      logFile.print(accl[1]);
      logFile.print(",\"z\":");
      logFile.print(accl[2]);
      logFile.print("}");
    } else {
      logFile.print("{\"S\":0");
    }

    logFile.print(",\"GPS\":");
    
    digitalWrite( PIN_STATUS_LED,1 ); //Turn off status led while reading from gps
    readGps();
    digitalWrite( PIN_STATUS_LED,0 ); //Turn on status led while writing to sd
    logFile.print(gpsStr);

    logFile.println("}");  

    if( btnPress() || numSamples == MAX_SAMPLES_PER_FILE)
    {
      logFile.println("] }");
      delay(5);
      logFile.flush();
      delay(5);
      logFile.close();
      delay(10);

      if( numSamples != MAX_SAMPLES_PER_FILE)
      {
        for(int i=0; i < 15; i++)
        {
          digitalWrite(6,0);
          delay(30);
          digitalWrite(6,1);
          delay(30);
        }
        delay(500);
      } else {
        beginRecordHack=1;
      }

      state=0;
    }    

  } 

}

//http://pansenti.wordpress.com/2013/03/26/pansentis-invensense-mpu-9150-software-for-arduino-is-now-on-github/
//Thank you to pansenti for setup code.
//I will documented this one later.
void MPU9150_setupCompass(){
  MPU9150_I2C_ADDRESS = 0x0C;      //change Adress to Compass

  MPU9150_writeSensor(0x0A, 0x00); //PowerDownMode
  MPU9150_writeSensor(0x0A, 0x0F); //SelfTest
  MPU9150_writeSensor(0x0A, 0x00); //PowerDownMode

  MPU9150_I2C_ADDRESS = 0x68;      //change Adress to MPU

  MPU9150_writeSensor(0x24, 0x40); //Wait for Data at Slave0
  MPU9150_writeSensor(0x25, 0x8C); //Set i2c address at slave0 at 0x0C
  MPU9150_writeSensor(0x26, 0x02); //Set where reading at slave 0 starts
  MPU9150_writeSensor(0x27, 0x88); //set offset at start reading and enable
  MPU9150_writeSensor(0x28, 0x0C); //set i2c address at slv1 at 0x0C
  MPU9150_writeSensor(0x29, 0x0A); //Set where reading at slave 1 starts
  MPU9150_writeSensor(0x2A, 0x81); //Enable at set length to 1
  MPU9150_writeSensor(0x64, 0x01); //overvride register
  MPU9150_writeSensor(0x67, 0x03); //set delay rate
  MPU9150_writeSensor(0x01, 0x80);

  MPU9150_writeSensor(0x34, 0x04); //set i2c slv4 delay
  MPU9150_writeSensor(0x64, 0x00); //override register
  MPU9150_writeSensor(0x6A, 0x00); //clear usr setting
  MPU9150_writeSensor(0x64, 0x01); //override register
  MPU9150_writeSensor(0x6A, 0x20); //enable master i2c mode
  MPU9150_writeSensor(0x34, 0x13); //disable slv4
}

////////////////////////////////////////////////////////////
///////// I2C functions to get easier all values ///////////
////////////////////////////////////////////////////////////

int MPU9150_readSensor(int addrL, int addrH){
  Wire.beginTransmission(MPU9150_I2C_ADDRESS);
  Wire.write(addrL);
  Wire.endTransmission(false);

  Wire.requestFrom(MPU9150_I2C_ADDRESS, 1, true);
  byte L = Wire.read();

  Wire.beginTransmission(MPU9150_I2C_ADDRESS);
  Wire.write(addrH);
  Wire.endTransmission(false);

  Wire.requestFrom(MPU9150_I2C_ADDRESS, 1, true);
  byte H = Wire.read();

  return (H<<8)+L;
}

int MPU9150_readSensor(int addr){
  Wire.beginTransmission(MPU9150_I2C_ADDRESS);
  Wire.write(addr);
  Wire.endTransmission(false);

  Wire.requestFrom(MPU9150_I2C_ADDRESS, 1, true);
  return Wire.read();
}

int MPU9150_writeSensor(int addr,int data){
  Wire.beginTransmission(MPU9150_I2C_ADDRESS);
  Wire.write(addr);
  Wire.write(data);
  Wire.endTransmission(true);

  return 1;
}

